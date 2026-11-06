"""Back up and restore-verify the local Docker database before a maintenance migration.

Outputs metadata only. A successful run leaves web/phpMyAdmin stopped for the migration.
"""
import argparse
from datetime import datetime, timezone, timedelta
import hashlib
import json
from pathlib import Path
import re
import secrets
import subprocess
import zipfile


def run(arguments, **kwargs):
    return subprocess.run(arguments, check=True, stderr=subprocess.PIPE, **kwargs)


def mysql(*arguments, **kwargs):
    command = ['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c',
               'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"\nexec mysql -uroot "$@"', 'mysql', *arguments]
    return run(command, **kwargs)


def fingerprint(schema=None):
    command = ['docker', 'compose', 'run', '--rm', '--no-deps', '-T', 'web', 'php', 'scripts/normalization-fingerprint.php']
    if schema:
        command.append(schema)
    return json.loads(run(command, stdout=subprocess.PIPE).stdout)


def digest(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def backup(directory):
    # A pre-existing backup directory must never be overwritten.
    directory.mkdir(parents=True, exist_ok=False)
    run(['docker', 'compose', 'stop', 'web', 'phpmyadmin'], stdout=subprocess.PIPE)
    restored = False
    clone = 'gnci_personnel_restore_' + secrets.token_hex(6)
    try:
        before = fingerprint()
        schema = before['source_schema']
        if not re.fullmatch(r'[A-Za-z0-9_]+', schema):
            raise RuntimeError('Unsupported source schema name')
        scheduler = mysql('-N', '-B', '-e', 'SELECT @@event_scheduler', stdout=subprocess.PIPE).stdout.strip()
        events = int(mysql('-N', '-B', '-e', f"SELECT COUNT(*) FROM information_schema.events WHERE event_schema='{schema}'", stdout=subprocess.PIPE).stdout.strip())
        if scheduler != b'OFF' and events:
            raise RuntimeError('Restore verification of scheduled events requires the local event scheduler to be OFF')
        dump = directory / 'database.sql'
        with dump.open('wb') as output:
            run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c',
                 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"\nexec mysqldump -uroot --single-transaction --routines --events --hex-blob --no-tablespaces "$@"',
                 'mysqldump', schema], stdout=output)
        mysql('-e', f'CREATE DATABASE `{clone}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', stdout=subprocess.PIPE)
        restored = True
        with dump.open('rb') as input_stream:
            mysql(clone, stdin=input_stream, stdout=subprocess.PIPE)
        after = fingerprint(clone)
        if before['tables'] != after['tables']:
            (directory / 'restore-comparison.json').write_text(json.dumps({'before':before,'restored':after},indent=2),encoding='utf-8')
            changed = [name for name in set(before['tables']) | set(after['tables']) if before['tables'].get(name) != after['tables'].get(name)]
            print(json.dumps({'restore_mismatches':sorted(changed)}),flush=True)
            raise RuntimeError('Restored database did not match the source counts, data hashes and schemas')
        uploads = directory / 'uploads.zip'
        with zipfile.ZipFile(uploads, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
            for path in Path('uploads').rglob('*'):
                if path.is_file():
                    archive.write(path, path.as_posix())
        with zipfile.ZipFile(uploads) as archive:
            if archive.testzip() is not None:
                raise RuntimeError('Upload archive CRC verification failed')
            for entry in archive.infolist():
                if hashlib.sha256(archive.read(entry)).hexdigest() != digest(Path(entry.filename)):
                    raise RuntimeError('Upload archive content verification failed')
        manifest = {'created_at': datetime.now(timezone(timedelta(hours=8))).isoformat(),
                    'database_sha256': digest(dump), 'uploads_sha256': digest(uploads),
                    'verified_tables': len(before['tables']), 'source': before}
        (directory / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
        print(json.dumps({'backup_directory': str(directory), 'verified_tables': len(before['tables']),
                          'database_restore_verified': True, 'uploads_verified': True,
                          'web_and_phpmyadmin': 'stopped for migration'}))
    except Exception:
        # No migration has run; resume the unchanged application after a backup failure.
        run(['docker', 'compose', 'start', 'web', 'phpmyadmin'], stdout=subprocess.PIPE)
        raise
    finally:
        if restored and re.fullmatch(r'gnci_personnel_restore_[a-f0-9]{12}', clone):
            mysql('-e', f'DROP DATABASE `{clone}`', stdout=subprocess.PIPE)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--directory', type=Path, required=True)
    options = parser.parse_args()
    backup(options.directory)
