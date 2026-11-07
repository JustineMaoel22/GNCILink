"""One-shot, local YuNet/SFace comparison. Embeddings exist only in process memory."""
import argparse
import json
import math
from pathlib import Path
import sys


def cosine_score(value):
    """SFace float32 dot products can overshoot +/-1 by a few ulps."""
    score = float(value)
    if not math.isfinite(score) or score < -1.000001 or score > 1.000001:
        raise ValueError('Invalid cosine score')
    return max(-1.0, min(1.0, score))


def compare(payload, models):
    import cv2
    import numpy as np

    cv2.setNumThreads(1)
    detector = cv2.FaceDetectorYN.create(str(models / 'face_detection_yunet_2023mar.onnx'), '', (320, 320), 0.9)
    recognizer = cv2.FaceRecognizerSF.create(str(models / 'face_recognition_sface_2021dec.onnx'), '')

    def features(path):
        image = cv2.imdecode(np.fromfile(path, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None or image.shape[0] * image.shape[1] > 40000000:
            raise ValueError('Invalid portrait')
        scale = min(1.0, 1024 / max(image.shape[:2]))
        if scale < 1:
            image = cv2.resize(image, None, fx=scale, fy=scale)
        detector.setInputSize((image.shape[1], image.shape[0]))
        _, faces = detector.detect(image)
        if faces is None:
            return []
        return [recognizer.feature(recognizer.alignCrop(image, face)).copy() for face in faces]

    candidate = features(payload['image'])
    if len(candidate) != 1:
        return {'complete': True, 'faces': len(candidate), 'scores': {}}
    scores = {}
    # Reused image files are decoded once per invocation, never cached on disk.
    cache = {}
    for profile in payload['profiles']:
        path = profile['path']
        if path not in cache:
            cache[path] = features(path)
        faces = cache[path]
        if not faces:
            # A non-face legacy image cannot match a face; still count it as checked.
            scores[profile['profile_key']] = -1.0
            continue
        scores[profile['profile_key']] = max(cosine_score(recognizer.match(candidate[0], face, cv2.FaceRecognizerSF_FR_COSINE)) for face in faces)
    return {'complete': True, 'faces': 1, 'scores': scores}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--models', required=True, type=Path)
    args = parser.parse_args()
    try:
        payload = json.loads(sys.stdin.buffer.read(1048576))
        print(json.dumps(compare(payload, args.models), allow_nan=False))
    except Exception:
        # Never print images, paths, embeddings or provider diagnostics.
        print(json.dumps({'complete': False}))
        sys.exit(1)
