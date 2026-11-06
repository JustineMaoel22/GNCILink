"""Verify only the reviewed tables disappeared and every remaining table is unchanged."""
import argparse
import json
from pathlib import Path
import subprocess

parser=argparse.ArgumentParser()
parser.add_argument('--directory',type=Path,required=True)
parser.add_argument('--capture-features',action='store_true')
args=parser.parse_args()
feature_command=['docker','compose','run','--rm','--no-deps','-T','web','php','scripts/cleanup-feature-state.php']
feature_result=subprocess.run(feature_command,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
features=json.loads(feature_result.stdout)
baseline=args.directory/'feature-baseline.json'
if args.capture_features:
    with baseline.open('x',encoding='utf-8') as output:
        json.dump(features,output,indent=2)
    print(json.dumps({'affected_feature_baselines_captured':len(features)}))
    raise SystemExit(0)
before=json.loads((args.directory/'manifest.json').read_text(encoding='utf-8'))['source']['tables']
removed={'office_services','program_sections','community_extension_page'}
command=['docker','compose','run','--rm','--no-deps','-T','web','php','scripts/normalization-fingerprint.php']
result=subprocess.run(command,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
after=json.loads(result.stdout)['tables']
if set(after)!=set(before)-removed:
    raise RuntimeError('Unexpected tables were added, removed, or retained')
changed=[name for name in after if any(before[name][field]!=after[name][field] for field in ['rows','data_sha256','schema_sha256'])]
if changed:
    raise RuntimeError('Remaining tables differ from the verified backup: '+', '.join(changed))
expected_features=json.loads(baseline.read_text(encoding='utf-8'))
if features!=expected_features:
    raise RuntimeError('Affected feature outputs differ from the pre-cleanup baseline')
print(json.dumps({'removed_tables':sorted(removed),'remaining_tables_verified':len(after),'all_remaining_records_and_schemas_unchanged':True,'affected_feature_outputs_unchanged':len(features)}))
