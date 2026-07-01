#!/usr/bin/env bash
#
# Install a static ffmpeg/ffprobe build during the Laravel Cloud build phase.
#
# Laravel Cloud does not persist system packages, so this downloads the latest
# static release into $HOME/bin/ffmpeg on every deploy. At runtime the binaries
# are reachable at /var/www/bin/ffmpeg/ffmpeg and .../ffprobe.
#
# Register it as a build command in the Laravel Cloud dashboard:
#   bash deploy/ffmpeg.sh
# and set the environment variable:
#   GALLERY_FFMPEG_PATH=/var/www/bin/ffmpeg/ffmpeg
#
set -euo pipefail

target="$HOME/bin/ffmpeg"
mkdir -p "$target"

echo "Downloading the latest static ffmpeg release..."
tmp="$(mktemp -d)"
curl -fsSL "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz" -o "$tmp/ffmpeg.tar.xz"

# The archive contains a single versioned directory; flatten it into $target.
tar -xf "$tmp/ffmpeg.tar.xz" --strip-components=1 -C "$target"
rm -rf "$tmp"

"$target/ffmpeg" -version | head -n 1
echo "ffmpeg installed at $target/ffmpeg"
