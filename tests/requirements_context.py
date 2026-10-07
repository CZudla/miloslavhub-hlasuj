"""Synthetic tests of the read-only REQUIREMENTS adapter; no external requests."""
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('context_adapter', ROOT/'scripts/requirements_context.py')
adapter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(adapter)


def item(number=1):
    return dict(key=f'hlasuj:requirement:HLS-{number:03}', id=f'HLS-{number:03}',
                service_id='hlasuj', kind='requirement', title='Syntetický požadavek',
                requirement_text='Student čeká na učitele.', acceptance_criteria='Nevytvoří se hlas.',
                revision=1, approval_status='unknown', implementation_status='unknown',
                verification_status='unverified', target_release=None)


def page(items=None, **overrides):
    return dict(system='hlasuj', mode='all', registry_version='synthetic-1',
                snapshot_sha256='a'*64, items=items or [item()], count=1,
                next_cursor=None, **overrides)


class ContextTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        (ROOT/'runtime').mkdir(exist_ok=True)

    def test_unknown_is_preserved_and_internal_fields_are_removed(self):
        entry = item(); entry['original_fields'] = {'private': 'do not copy'}
        entry['source_status'] = 'Verified in an old spreadsheet'
        result = adapter.from_api('synthetic-token', lambda query, token: page([entry]))
        self.assertEqual(result['approval_counts'], {'unknown': 1})
        self.assertEqual(result['items'][0]['verification_status'], 'unverified')
        self.assertNotIn('do not copy', json.dumps(result))
        self.assertNotIn('original_fields', result['items'][0])
        self.assertNotIn('source_status', result['items'][0])
        self.assertFalse(result['authoritative_write_performed'])

    def test_all_pages_are_read_with_same_selectors(self):
        calls = []
        def fetch(query, token):
            calls.append(query.copy())
            data = page([item(len(calls))]); data['count'] = 2
            data['next_cursor'] = 'opaque-cursor' if len(calls) == 1 else None
            return data
        result = adapter.from_api('synthetic-token', fetch)
        self.assertEqual(result['count'], 2)
        self.assertEqual(calls[1], {**calls[0], 'cursor': 'opaque-cursor'})
        self.assertEqual(calls[0]['mode'], 'all')

    def test_broken_pages_fail_closed(self):
        cases = [
            {'system': 'auth'}, {'mode': 'active'}, {'items': 'bad'},
            {'count': True}, {'count': 0}, {'count': 2},
            {'snapshot_sha256': 'bad'}, {'registry_version': None},
            {'next_cursor': 7}, {'items': [] , 'next_cursor': 'cursor'},
        ]
        for change in cases:
            with self.subTest(change=change), self.assertRaises(adapter.ContextError):
                adapter.from_api('synthetic-token', lambda query, token: {**page(), **change})

    def test_duplicate_keys_are_not_silently_merged(self):
        with self.assertRaises(adapter.ContextError):
            adapter.from_api('synthetic-token', lambda query, token: {**page([item(), item()]), 'count': 2})

    def test_changed_baseline_and_count_are_rejected(self):
        for change in ({'registry_version': 'new'}, {'snapshot_sha256': 'b'*64}, {'count': 3}):
            calls = []
            def fetch(query, token):
                calls.append(query)
                return {**page([item(len(calls))]), 'count': 2,
                        'next_cursor': 'cursor' if len(calls) == 1 else None,
                        **(change if len(calls) > 1 else {})}
            with self.subTest(change=change), self.assertRaises(adapter.ContextError):
                adapter.from_api('synthetic-token', fetch)

    def test_repeated_cursor_is_rejected(self):
        with self.assertRaises(adapter.ContextError):
            adapter.from_api('synthetic-token', lambda query, token: {**page(), 'next_cursor': 'repeated'})

    def test_items_require_hlasuj_keys_and_integer_revisions(self):
        for change in ({'service_id': 'auth'}, {'key': 'auth:requirement:HLS-001'},
                       {'kind': 'other'}, {'revision': True}, {'revision': 0},
                       {'title': []}, {'target_release': {}}, {'id': ''}):
            with self.subTest(change=change), self.assertRaises(adapter.ContextError):
                adapter.validate_item({**item(), **change})
        missing = item(); del missing['acceptance_criteria']
        with self.assertRaises(adapter.ContextError):
            adapter.validate_item(missing)

    def test_export_pin_and_secondary_provenance(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/'registry.json'
            raw = json.dumps({'registry_version': 'synthetic-1', 'entries': [item(), {**item(2), 'service_id': 'auth'}]}).encode()
            path.write_bytes(raw)
            result = adapter.from_export(path, hashlib.sha256(raw).hexdigest())
            self.assertEqual(result['count'], 1)
            self.assertFalse(result['live_api_verified'])
            self.assertEqual(result['source'], 'pinned-release-export')
            with self.assertRaises(adapter.ContextError):
                adapter.from_export(path, '0'*64)
            with self.assertRaises(adapter.ContextError):
                adapter.from_export(path, '')

    def test_bad_credentials_never_reach_transport(self):
        for token in ('', 'line\nbreak', 'tab\t', 'not ascii č', 'space token'):
            with self.subTest(token=token), self.assertRaises(adapter.ContextError):
                adapter.from_api(token, lambda *args: self.fail('Network must not run'))

    def test_malformed_export_records_are_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/'registry.json'
            for record in (None, 'bad', 7, []):
                raw = json.dumps({'registry_version': 'synthetic-1', 'entries': [item(), record]}).encode()
                path.write_bytes(raw)
                with self.subTest(record=record), self.assertRaises(adapter.ContextError):
                    adapter.from_export(path, hashlib.sha256(raw).hexdigest())

    def test_transport_validates_version_type_and_size(self):
        from email.message import Message
        from unittest.mock import MagicMock
        for version, content_type, body in (
                ('2', 'application/json', b'{}'),
                ('1', 'text/html', b'<html>synthetic</html>'),
                ('1', 'application/json', b'x'*1048577)):
            response = MagicMock(); headers = Message()
            headers['X-API-Version'] = version; headers['Content-Type'] = content_type
            response.__enter__.return_value = response; response.headers = headers
            response.read.return_value = body
            opener = MagicMock(); opener.open.return_value = response
            with self.subTest(version=version, content_type=content_type, size=len(body)), \
                 patch.object(adapter.urllib.request, 'build_opener', return_value=opener), \
                 self.assertRaises(adapter.ContextError):
                adapter.get_page({'mode': 'all'}, 'synthetic-token')
            request = opener.open.call_args.args[0]
            self.assertEqual(request.get_method(), 'GET')
            self.assertTrue(request.full_url.startswith(adapter.ORIGIN+'/api/v1/context/hlasuj?'))
            self.assertEqual(request.get_header('Authorization'), 'Bearer synthetic-token')
            self.assertEqual(opener.open.call_args.kwargs['timeout'], 20)

    def test_redirect_does_not_forward_authorization(self):
        request = adapter.urllib.request.Request(adapter.ORIGIN, headers={'Authorization': 'Bearer synthetic'})
        with self.assertRaises(adapter.ContextError):
            adapter.NoRedirect().redirect_request(request, None, 302, 'redirect', {}, 'https://example.invalid')

    def test_output_cannot_enter_distribution_or_tracked_docs(self):
        with self.assertRaises(adapter.ContextError):
            adapter.save_context({'internal': True}, ROOT/'docs/central-context.json')

    def test_safe_atomic_output(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'runtime') as folder:
            output = Path(folder)/'context.json'
            adapter.save_context({'revision': 1}, output)
            adapter.save_context({'revision': 2}, output)
            self.assertEqual(json.loads(output.read_bytes()), {'revision': 2})
            self.assertEqual(len(list(Path(folder).iterdir())), 1)

    def test_failure_keeps_previous_context_and_redacts_exception(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'runtime') as folder:
            output = Path(folder)/'context.json'; output.write_text('previous')
            with patch('sys.argv', ['context', '--output', str(output)]), \
                 patch.object(adapter, 'from_api', side_effect=adapter.ContextError('SECRET')), \
                 patch('sys.stderr') as stderr:
                self.assertEqual(adapter.main(), 1)
                self.assertNotIn('SECRET', str(stderr.write.call_args_list))
            self.assertEqual(output.read_text(), 'previous')


if __name__ == '__main__':
    unittest.main(verbosity=2)
