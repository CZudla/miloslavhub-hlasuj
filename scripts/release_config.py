"""Shared release metadata; archived releases are not packaging input."""
from pathlib import Path
import json, re
ROOT=Path(__file__).resolve().parents[1]
RELEASE=json.loads((ROOT/'release-config.json').read_text(encoding='utf8'))
VERSION=RELEASE['version']
SCHEMA=RELEASE['schema_version']
assert re.fullmatch(r'\d+\.\d+\.\d+',VERSION)
def source_path(name):return not name.startswith('releases/')
