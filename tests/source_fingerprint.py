"""Hash the application/test inputs, using the qualified packaging convention."""
import hashlib


def source_sha256(root):
    sources = []
    for folder in ('frontend', 'wordpress', 'tests'):
        sources.extend(path for path in (root/folder).rglob('*')
                       if path.is_file() and path.name not in ('config.php', 'manifest.json')
                       and '__pycache__' not in path.parts)
    return hashlib.sha256(b''.join(
        path.relative_to(root).as_posix().encode()+b'\0'+path.read_bytes()
        for path in sorted(sources, key=lambda path: path.relative_to(root).as_posix())
    )).hexdigest()
