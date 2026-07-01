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

# Pick the static build matching the build machine's architecture. Laravel
# Cloud builders are arm64; a mismatched binary fails with "Exec format error".
case "$(uname -m)" in
    x86_64 | amd64) arch="amd64" ;;
    aarch64 | arm64) arch="arm64" ;;
    armv7l | armv6l | armhf) arch="armhf" ;;
    i686 | i386) arch="i686" ;;
    *) echo "Unsupported architecture: $(uname -m)" >&2; exit 1 ;;
esac

echo "Downloading the latest static ffmpeg release ($arch)..."
tmp="$(mktemp -d)"
curl -fsSL "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-${arch}-static.tar.xz" -o "$tmp/ffmpeg.tar.xz"

# The archive contains a single versioned directory; flatten it into $target.
tar -xf "$tmp/ffmpeg.tar.xz" --strip-components=1 -C "$target"
rm -rf "$tmp"

"$target/ffmpeg" -version | head -n 1
echo "ffmpeg installed at $target/ffmpeg"
