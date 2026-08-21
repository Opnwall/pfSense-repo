#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-pfSense-pkg-easytier}"
VERSION="${VERSION:-1.0.4}"
ORIGIN="${ORIGIN:-net/pfSense-pkg-easytier}"
COMMENT="${COMMENT:-EasyTier mesh VPN integration for pfSense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/EasyTier/EasyTier}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-universal}}"
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"
unset ABI || true

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-$SCRIPT_DIR/work/freebsd-pkg}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-$SCRIPT_DIR/dist}"

die() { echo "error: $*" >&2; exit 1; }
command -v pkg >/dev/null 2>&1 || die "pkg is required; build on FreeBSD or pfSense"
command -v tar >/dev/null 2>&1 || die "tar is required"

case "$TARGET_ABI" in
	universal) PKG_ABI="FreeBSD:*:amd64"; PKG_ARCH="freebsd:*:x86:64" ;;
	native) PKG_ABI="$(pkg config ABI)"; PKG_ARCH="freebsd:$(echo "$PKG_ABI" | awk -F: '{print $2}'):x86:64" ;;
	FreeBSD:*:amd64) PKG_ABI="$TARGET_ABI"; PKG_ARCH="freebsd:$(echo "$PKG_ABI" | awk -F: '{print $2}'):x86:64" ;;
	*) die "unsupported ABI: $TARGET_ABI" ;;
esac

for file in \
	src/etc/rc.conf.d/easytier \
	src/usr/local/etc/rc.d/easytier \
	src/usr/local/pkg/easytier.inc \
	src/usr/local/pkg/easytier.xml \
	src/usr/local/sbin/easytier-core \
	src/usr/local/sbin/easytier-cli \
	src/usr/local/share/pfSense-pkg-easytier/config.toml.sample \
	src/usr/local/share/pfSense-pkg-easytier/info.xml \
	src/usr/local/share/locale/zh_CN/LC_MESSAGES/pfSense-pkg-easytier.mo \
	src/usr/local/share/locale/zh_Hans_CN/LC_MESSAGES/pfSense-pkg-easytier.mo \
	src/usr/local/www/vpn_easytier.php; do
	[ -f "$SCRIPT_DIR/$file" ] || die "missing $file"
done

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR"
(cd "$SCRIPT_DIR/src" && tar --exclude '.DS_Store' -cf - .) | (cd "$STAGEDIR" && tar -xf -)
chmod 0644 "$STAGEDIR/etc/rc.conf.d/easytier"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/easytier" \
	"$STAGEDIR/usr/local/sbin/easytier-core" \
	"$STAGEDIR/usr/local/sbin/easytier-cli"

find "$STAGEDIR" -type f | sed "s#^$STAGEDIR##" | sort > "$PLIST"
FLATSIZE=0
while IFS= read -r file; do
	size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"
	FLATSIZE=$((FLATSIZE + size))
done < "$PLIST"

sed -e "s#@PKG_NAME@#$PKG_NAME#g" -e "s#@ORIGIN@#$ORIGIN#g" \
	-e "s#@VERSION@#$VERSION#g" -e "s#@COMMENT@#$COMMENT#g" \
	-e "s#@MAINTAINER@#$MAINTAINER#g" -e "s#@WWW@#$WWW#g" \
	-e "s#@ABI@#$PKG_ABI#g" -e "s#@ARCH@#$PKG_ARCH#g" \
	-e "s#@PREFIX@#$PREFIX#g" -e "s#@FLATSIZE@#$FLATSIZE#g" \
	-e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" -e "/@DESC@/d" \
	"$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"
for hook in +POST_INSTALL +PRE_DEINSTALL +POST_DEINSTALL; do
	install -m 0644 "$SCRIPT_DIR/packaging/freebsd/$hook" "$METADIR/$hook"
done

pkg create -e -f "$FORMAT" -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"
CREATED="$DISTDIR/$PKG_NAME-$VERSION.pkg"
[ -f "$CREATED" ] || die "package was not created"
mv -f "$CREATED" "$DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME" >/dev/null
echo "Package: $DISTDIR/$OUTPUT_NAME"
sha256 "$DISTDIR/$OUTPUT_NAME"
