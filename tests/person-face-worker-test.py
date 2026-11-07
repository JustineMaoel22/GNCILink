"""Integration test with local model files and an existing portrait, no network calls."""
import argparse
import importlib.util
import tempfile
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--models', type=Path, required=True)
parser.add_argument('--portrait', type=Path, required=True)
args = parser.parse_args()
import cv2
import numpy as np

spec = importlib.util.spec_from_file_location('face_worker', Path(__file__).parents[1] / 'scripts/person-face-check.py')
worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)
assert worker.cosine_score(1.0000000810496772) == 1.0
assert worker.cosine_score(-1.0000000810496772) == -1.0
assert worker.cosine_score(.55) == .55
for invalid in [1.001, -1.001, float('nan'), float('inf')]:
    try:
        worker.cosine_score(invalid)
        raise AssertionError('Invalid similarity accepted')
    except ValueError:
        pass
with tempfile.TemporaryDirectory() as directory:
    root = Path(directory)
    image = cv2.imdecode(np.fromfile(args.portrait, dtype=np.uint8), cv2.IMREAD_COLOR)
    assert image is not None
    resized = cv2.resize(image, None, fx=0.7, fy=0.7)
    cv2.imwrite(str(root / 'different-name.jpg'), resized)
    # Crop the photo perimeter and change the canvas/background around it.
    h, w = image.shape[:2]
    cropped = image[int(h*.05):int(h*.95), int(w*.05):int(w*.95)]
    cropped = cv2.copyMakeBorder(cropped, 25, 25, 25, 25, cv2.BORDER_CONSTANT, value=(210, 100, 30))
    cv2.imwrite(str(root / 'cropped.jpg'), cropped)
    cv2.imwrite(str(root / 'blank.jpg'), np.full((320, 320, 3), 255, dtype=np.uint8))
    profiles = [{'profile_key': key, 'path': str(root / filename)} for key, filename in [
        ('resized', 'different-name.jpg'), ('crop', 'cropped.jpg'), ('blank', 'blank.jpg')]]
    profiles.append({'profile_key': 'identical', 'path': str(args.portrait)})
    result = worker.compare({'image': str(args.portrait), 'profiles': profiles}, args.models)
    assert result['complete'] and result['faces'] == 1, result
    assert result['scores']['resized'] >= .55, result
    assert result['scores']['crop'] >= .55, result
    assert result['scores']['blank'] == -1, result
    assert .99 <= result['scores']['identical'] <= 1, result
    assert all(-1 <= score <= 1 for score in result['scores'].values()), result
    blank = worker.compare({'image': str(root/'blank.jpg'), 'profiles': profiles}, args.models)
    assert blank['faces'] == 0 and blank['scores'] == {}, blank
    group = np.concatenate([image, image], axis=1)
    cv2.imwrite(str(root/'group.jpg'), group)
    group_result = worker.compare({'image':str(root/'group.jpg'),'profiles':profiles},args.models)
    assert group_result['faces'] > 1, group_result
print('Face worker: resized, cropped, changed canvas, no-face and multiple-face checks passed.')
