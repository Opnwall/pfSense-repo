#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-pfSense-pkg-speedtest}"
VERSION="${VERSION:-1.0.1}"
TARGET_ABI="${TARGET_ABI:-${ABI:-universal}}"
ENGINE_VERSION="${ENGINE_VERSION:-1.7.10}"
ENGINE_ASSET="speedtest-go_${ENGINE_VERSION}_Freebsd_x86_64.tar.gz"
ENGINE_SHA256="${ENGINE_SHA256:-648ea262297aff61dbe8569f8b719b40f7eb928cc9b17d13927282365a857f80}"
ENGINE_URL="https://github.com/showwin/speedtest-go/releases/download/v${ENGINE_VERSION}/${ENGINE_ASSET}"
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-$SCRIPT_DIR/work/freebsd-pkg}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-$SCRIPT_DIR/dist}"

command -v pkg >/dev/null 2>&1 || { echo "Run this script on FreeBSD/pfSense." >&2; exit 1; }

case "$TARGET_ABI" in
	universal) PKG_ABI='FreeBSD:*:amd64'; PKG_ARCH='freebsd:*:x86:64' ;;
	native) PKG_ABI="$(pkg config ABI)"; PKG_ARCH="freebsd:$(echo "$PKG_ABI" | awk -F: '{print $2}'):x86:64" ;;
	FreeBSD:*:amd64) PKG_ABI="$TARGET_ABI"; PKG_ARCH="freebsd:$(echo "$PKG_ABI" | awk -F: '{print $2}'):x86:64" ;;
	*) echo "Unsupported ABI: $TARGET_ABI" >&2; exit 1 ;;
esac

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR/usr/local/bin" "$METADIR" "$DISTDIR" "$WORKDIR/download"
(cd "$SCRIPT_DIR/src" && tar --exclude '.DS_Store' -cf - .) | (cd "$STAGEDIR" && tar -xf -)

archive="$WORKDIR/download/$ENGINE_ASSET"
if [ -f "$SCRIPT_DIR/src/usr/local/bin/$ENGINE_ASSET" ]; then
	cp "$SCRIPT_DIR/src/usr/local/bin/$ENGINE_ASSET" "$archive"
else
	fetch -q -T 300 -o "$archive" "$ENGINE_URL"
fi
actual="$(sha256 -q "$archive")"
[ "$actual" = "$ENGINE_SHA256" ] || { echo "speedtest-go checksum mismatch" >&2; exit 1; }
tar -xzf "$archive" -C "$WORKDIR/download"
binary="$(find "$WORKDIR/download" -type f -name speedtest-go | head -1)"
[ -n "$binary" ] || { echo "speedtest-go binary not found" >&2; exit 1; }
install -m 0755 "$binary" "$STAGEDIR/usr/local/bin/opnwall-speedtest"
rm -f "$STAGEDIR/usr/local/bin/$ENGINE_ASSET"
install -m 0644 "$SCRIPT_DIR/third_party/speedtest-go.LICENSE" "$STAGEDIR/usr/local/share/pfSense-pkg-speedtest/speedtest-go.LICENSE"

find "$STAGEDIR" -type f | sed "s#^$STAGEDIR##" | sort > "$PLIST"
FLATSIZE="$(find "$STAGEDIR" -type f -exec stat -f %z {} \; | awk '{n += $1} END {print n + 0}')"
sed \
	-e "s#@PKG_NAME@#$PKG_NAME#g" \
	-e "s#@VERSION@#$VERSION#g" \
	-e "s#@ABI@#$PKG_ABI#g" \
	-e "s#@ARCH@#$PKG_ARCH#g" \
	-e "s#@FLATSIZE@#$FLATSIZE#g" \
	-e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" \
	-e '/@DESC@/d' \
	"$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"
for script in +POST_INSTALL +PRE_DEINSTALL +POST_DEINSTALL; do
	install -m 0644 "$SCRIPT_DIR/packaging/freebsd/$script" "$METADIR/$script"
done
pkg create -e -f tgz -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"
created="$DISTDIR/$PKG_NAME-$VERSION.pkg"
[ ! -f "$created" ] || mv -f "$created" "$DISTDIR/$PKG_NAME.pkg"
pkg info -F "$DISTDIR/$PKG_NAME.pkg" >/dev/null
echo "Package: $DISTDIR/$PKG_NAME.pkg"
sha256 "$DISTDIR/$PKG_NAME.pkg"
