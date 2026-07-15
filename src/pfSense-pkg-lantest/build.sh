#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-pfSense-pkg-lantest}"
VERSION="${VERSION:-1.0.1}"
TARGET_ABI="${TARGET_ABI:-${ABI:-universal}}"
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
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR"
(cd "$SCRIPT_DIR/src" && tar --exclude '.DS_Store' -cf - .) | (cd "$STAGEDIR" && tar -xf -)
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/lanspeedtest"
chmod 0644 "$STAGEDIR/usr/local/libexec/lanspeedtest/router.php"

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
