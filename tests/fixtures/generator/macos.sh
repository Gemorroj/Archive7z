#!/usr/bin/env bash
#
# Generates the macOS fixtures into tests/fixtures/macos/.
#
# Must run on macOS: it relies on `ditto` (the Apple Archive Utility engine),
# which is what produces the Apple-specific quirks we want to cover:
#   - Host OS = Mac OS in the ZIP "version made by"
#   - NFD (decomposed) Unicode file names
#   - __MACOSX / AppleDouble resource-fork entries
#
# Used by .github/workflows/fixtures-macos.yml; maintainers may also run it on
# a Mac. The payload is taken from an already committed Linux tar so that every
# archive shares the same contents.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
fixtures="$(cd "$script_dir/.." && pwd)"

payload_tar="$fixtures/linux/gnutar-1.34/sample.tar"
if [ ! -f "$payload_tar" ]; then
    echo "payload source not found: $payload_tar" >&2
    exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/payload"
bsdtar -xf "$payload_tar" -C "$work/payload"

out="$fixtures/macos"
rm -rf "$out"
mkdir -p "$out/apple-archive" "$out/zip" "$out/bsdtar"

cd "$work/payload"

# Apple Archive Utility engine: adds __MACOSX, stores names in NFD.
ditto -c -k . "$out/apple-archive/sample.zip"

# Info-ZIP shipped with macOS.
zip -r -q "$out/zip/sample.zip" .

# libarchive bsdtar shipped with macOS.
bsdtar -cf "$out/bsdtar/sample.tar" *

echo "Generated macOS fixtures:"
find "$out" -type f -print
