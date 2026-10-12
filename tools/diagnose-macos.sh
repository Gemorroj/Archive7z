#!/usr/bin/env bash
# TEMPORARY diagnostic for macOS legacy (non-UTF-8) name decoding.
# Remove together with the "diagnose-macos" CI job once analysed.

set -u
set -x

echo "=== versions ==="
uname -a
sw_vers 2>/dev/null || true
php -v
php -r 'echo "PHP_OS_FAMILY=".PHP_OS_FAMILY.PHP_EOL;'
locale
LANG=C 7zz | head -3
command -v 7zz

FIX=tests/fixtures/tar/windows/totalcommander-9.21a/sample.tar
U8=$(printf '\xd1\x87\xd0\xb0\xd0\xb2\xd0\xb5\xd1\x81.jpg')
C1=$(printf '\xf7\xe0\xe2\xe5\xf1.jpg')

echo "=== expected bytes ==="
printf 'utf8:   '
printf '%s' "$U8" | od -An -tx1
printf 'cp1251: '
printf '%s' "$C1" | od -An -tx1

printf '%s\n' "$C1" > /tmp/lst.cp1251

echo "=== list, no mask: Path lines (hex) ==="
7zz l -slt "$FIX" | grep '^Path =' | od -An -tx1

echo "=== list, masked cp1251, -scs1251: Path lines (hex) ==="
7zz l -slt -mcp=1251 -scs1251 "$FIX" @/tmp/lst.cp1251 | grep '^Path =' | od -An -tx1

echo "=== list, masked cp1251, -scs1251 -sccUTF-8: Path lines (hex) ==="
7zz l -slt -mcp=1251 -sccUTF-8 -scs1251 "$FIX" @/tmp/lst.cp1251 | grep '^Path =' | od -An -tx1

echo "=== list, masked cp1251, no -scs: Path lines (hex) ==="
7zz l -slt -mcp=1251 "$FIX" @/tmp/lst.cp1251 | grep '^Path =' | od -An -tx1

echo "=== extract masked cp1251 -scs1251: on-disk names (hex) ==="
rm -rf /tmp/o && mkdir -p /tmp/o
7zz x -y -mcp=1251 -scs1251 -o/tmp/o "$FIX" @/tmp/lst.cp1251 >/dev/null 2>&1 || true
while IFS= read -r f; do
    b="${f##*/}"
    printf 'name_hex='
    printf '%s' "$b" | od -An -tx1
done < <(find /tmp/o -mindepth 1 -maxdepth 2 -print)

echo "=== library diagnostics ==="
php tools/diagnose-library.php "$FIX"

echo "=== done ==="
