"""Compare original column values with the verified pre-migration backup manifest."""
import argparse
import json
from pathlib import Path
import re
import subprocess

parser=argparse.ArgumentParser()
parser.add_argument('--directory',type=Path,required=True)
options=parser.parse_args()
manifest=json.loads((options.directory/'manifest.json').read_text(encoding='utf-8'))
before=manifest['source']['tables']
projection={name:re.findall(r'^\s+`([^`]+)` ',table['schema_definition'],flags=re.M) for name,table in before.items()}
command=['docker','compose','run','--rm','--no-deps','-T','web','php','scripts/normalization-fingerprint.php','--original-columns']
result=subprocess.run(command,input=json.dumps(projection).encode(),stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True)
after=json.loads(result.stdout)['tables']
changed=[name for name in before if name not in after or any(before[name][field]!=after[name][field] for field in ['rows','data_sha256'])]
if changed:
    raise RuntimeError('Original data verification failed for: '+', '.join(changed))
print(json.dumps({'original_tables_verified':len(before),'original_column_values_preserved':True,'new_migration_marker_excluded':True}))
