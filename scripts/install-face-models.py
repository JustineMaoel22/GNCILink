"""Install checksum-pinned OpenCV models outside the public document root."""
import argparse
import hashlib
from pathlib import Path
import urllib.request

MODELS = {
    'face_detection_yunet_2023mar.onnx': (
        'face_detection_yunet',
        '8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4',
    ),
    'face_recognition_sface_2021dec.onnx': (
        'face_recognition_sface',
        '0ba9fbfa01b5270c96627c4ef784da859931e02f04419c829e83484087c34e79',
    ),
}


def install(directory):
    directory.mkdir(parents=True, exist_ok=True)
    for filename, (folder, checksum) in MODELS.items():
        destination = directory / filename
        if destination.is_file() and hashlib.sha256(destination.read_bytes()).hexdigest() == checksum:
            continue
        url = f'https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/{folder}/{filename}'
        with urllib.request.urlopen(url, timeout=120) as response:
            content = response.read(50 * 1024 * 1024)
        if hashlib.sha256(content).hexdigest() != checksum:
            raise RuntimeError(f'Model checksum did not match: {filename}')
        temporary = destination.with_suffix('.download')
        temporary.write_bytes(content)
        temporary.replace(destination)
        destination.chmod(0o644)
    for folder in {model[0] for model in MODELS.values()}:
        url = f'https://raw.githubusercontent.com/opencv/opencv_zoo/main/models/{folder}/LICENSE'
        with urllib.request.urlopen(url, timeout=30) as response:
            (directory / f'{folder}-LICENSE').write_bytes(response.read(1024 * 1024))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('directory', type=Path)
    install(parser.parse_args().directory)
