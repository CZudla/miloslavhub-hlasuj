"""Read HLASUJ context from REQUIREMENTS. Never changes the authoritative registry.

Bearer credentials come only from the environment. An explicitly pinned local
release export is supported when the production API credential is unavailable.
"""
import argparse
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = 'https://requirements.miloslavhub.cz'
FIELDS = ('key', 'id', 'service_id', 'kind', 'title', 'requirement_text',
          'acceptance_criteria', 'revision', 'approval_status',
          'implementation_status', 'verification_status', 'target_release')


class ContextError(ValueError):
    pass


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def validate_item(item):
    if not isinstance(item, dict) or any(field not in item for field in FIELDS):
        raise ContextError('Neúplná položka centrálního registru.')
    if item['service_id'] != 'hlasuj' or item['kind'] not in ('requirement', 'decision'):
        raise ContextError('Kontext obsahuje položku jiné služby nebo neznámý druh.')
    if (not isinstance(item['id'], str) or not item['id'] or
            item['key'] != f"hlasuj:{item['kind']}:{item['id']}"):
        raise ContextError('Neplatný centrální klíč.')
    if type(item['revision']) is not int or item['revision'] < 1:
        raise ContextError('Neplatná revize požadavku.')
    for field in FIELDS:
        if field not in ('revision', 'target_release') and not isinstance(item[field], str):
            raise ContextError('Neplatný typ pole kontextu.')
    if item['target_release'] is not None and not isinstance(item['target_release'], str):
        raise ContextError('Neplatná cílová verze.')
    # Preserve status values exactly. Unknown is never promoted to approved/verified.
    return {field: item[field] for field in FIELDS}


def context_result(items, version, sha, source):
    if not isinstance(version, str) or not version or not re.fullmatch('[a-f0-9]{64}', sha):
        raise ContextError('Chybí verze nebo SHA-256 baseline.')
    cleaned = [validate_item(item) for item in items]
    if len({item['key'] for item in cleaned}) != len(cleaned):
        raise ContextError('Duplicitní klíče v kontextu.')
    return {
        'schema_version': 1, 'authority': ORIGIN, 'system': 'hlasuj',
        'source': source, 'registry_version': version, 'snapshot_sha256': sha,
        'retrieved_at': datetime.now(timezone.utc).isoformat(),
        'live_api_verified': source == 'private-api-v1',
        'authoritative_write_performed': False,
        'items': sorted(cleaned, key=lambda item: item['key']),
        'count': len(cleaned),
        'approval_counts': dict(Counter(item['approval_status'] for item in cleaned)),
        'limitations': ['Secondary context copy; re-read central baseline before implementation.',
                        'Only HLASUJ entries; global applicability is not inferred.',
                        'Source variants and full revision history require separate API reads.',
                        'Service Registry live versions are not connected.'],
    }


def from_export(path, expected_sha):
    if not re.fullmatch('[a-f0-9]{64}', expected_sha):
        raise ContextError('Export vyžaduje explicitní SHA-256 z manifestu REQUIREMENTS.')
    if path.stat().st_size > 32 * 1024 * 1024:
        raise ContextError('Export překračuje limit 32 MiB.')
    raw = path.read_bytes()
    if digest(raw) != expected_sha:
        raise ContextError('Export neodpovídá připnutému SHA-256.')
    data = json.loads(raw)
    if (not isinstance(data, dict) or not isinstance(data.get('entries'), list)
            or any(not isinstance(item, dict) for item in data['entries'])):
        raise ContextError('Neplatný export REQUIREMENTS.')
    items = [item for item in data['entries'] if item.get('service_id') == 'hlasuj']
    if not items:
        raise ContextError('Export neobsahuje HLASUJ.')
    return context_result(items, data.get('registry_version'), expected_sha, 'pinned-release-export')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        # Never forward Authorization to redirects, even on the same origin.
        raise ContextError('Přesměrování Private API je odmítnuto.')


def get_page(query, token):
    url = ORIGIN + '/api/v1/context/hlasuj?' + urllib.parse.urlencode(query)
    request = urllib.request.Request(url, headers={
        'Authorization': 'Bearer ' + token, 'Accept': 'application/json',
        'User-Agent': 'Hlasuj-Requirements-Context/1',
    })
    try:
        with urllib.request.build_opener(NoRedirect()).open(request, timeout=20) as response:
            if response.headers.get('X-API-Version') != '1':
                raise ContextError('Server nepotvrdil Private API major v1.')
            if response.headers.get_content_type() != 'application/json':
                raise ContextError('Private API nevrátilo JSON.')
            raw = response.read(1048577)
            if len(raw) > 1048576:
                raise ContextError('Odpověď Private API překračuje 1 MiB.')
            return json.loads(raw)
    except urllib.error.HTTPError as error:
        # Do not read/print server bodies, headers, cursors or credentials.
        raise ContextError(f'Private API vrátilo HTTP {error.code}.') from None
    except urllib.error.URLError:
        raise ContextError('Private API nelze bezpečně načíst; ověřte síť a TLS.') from None


def from_api(token, fetch=get_page):
    if not token or any(ord(c) <= 32 or ord(c) >= 127 for c in token):
        raise ContextError('Chybí platný strojový Bearer credential v prostředí.')
    items, cursors = [], set()
    version = sha = None
    cursor = None
    count = None
    for _ in range(100):
        query = {'mode': 'all', 'include': 'requirements,decisions,baselines', 'limit': 100}
        if cursor is not None:
            query['cursor'] = cursor
        page = fetch(query, token)
        if (not isinstance(page, dict) or page.get('system') != 'hlasuj' or
                page.get('mode') != 'all' or not isinstance(page.get('items'), list) or
                type(page.get('count')) is not int or page['count'] < 1):
            raise ContextError('Neplatná stránka kontextu.')
        if version is None:
            version, sha, count = page.get('registry_version'), page.get('snapshot_sha256'), page['count']
        if (version, sha, count) != (page.get('registry_version'), page.get('snapshot_sha256'), page['count']):
            raise ContextError('Baseline se změnila během čtení; spusťte nové načtení.')
        items.extend(page['items'])
        cursor = page.get('next_cursor')
        if cursor is None:
            if len(items) != count:
                raise ContextError('Načtený kontext je neúplný.')
            return context_result(items, version, sha, 'private-api-v1')
        if not isinstance(cursor, str) or not cursor or cursor in cursors or not page['items']:
            raise ContextError('Neplatné nebo opakované stránkování.')
        cursors.add(cursor)
    raise ContextError('Kontext překračuje bezpečný limit stránek.')


def save_context(data, output):
    output = output.resolve()
    # Context can contain internal specifications. Keep it out of customer ZIPs/Git.
    if not output.is_relative_to((ROOT / 'runtime').resolve()):
        raise ContextError('Pracovní kontext ukládejte pouze do ignorovaného runtime/.')
    output.parent.mkdir(parents=True, exist_ok=True)
    content = json.dumps(data, ensure_ascii=False, indent=2).encode('utf8') + b'\n'
    with tempfile.NamedTemporaryFile(dir=output.parent, delete=False) as temporary:
        temporary.write(content)
        temporary.flush()
        os.fsync(temporary.fileno())
        name = Path(temporary.name)
    try:
        os.replace(name, output)
    finally:
        name.unlink(missing_ok=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--export', type=Path)
    parser.add_argument('--sha256')
    parser.add_argument('--output', type=Path, default=ROOT/'runtime/requirements/context.json')
    args = parser.parse_args()
    try:
        if bool(args.export) != bool(args.sha256):
            raise ContextError('--export a --sha256 musí být zadány společně.')
        data = (from_export(args.export, args.sha256) if args.export else
                from_api(os.environ.get('MHL_REQUIREMENTS_READ_TOKEN', '')))
        save_context(data, args.output)
        print(json.dumps({k: data[k] for k in ('source', 'registry_version', 'count',
                          'approval_counts', 'live_api_verified', 'authoritative_write_performed')}, ensure_ascii=False))
        return 0
    except (ContextError, OSError, ValueError, TypeError, KeyError):
        # Errors may contain secret-bearing paths/payloads; output a fixed safe message.
        print('ERROR: Kontext REQUIREMENTS nebyl načten. Ověřte credential, dostupnost API nebo připnutý export; předchozí soubor zůstal zachován.', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
