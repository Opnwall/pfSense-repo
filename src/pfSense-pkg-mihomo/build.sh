#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-pfSense-pkg-mihomo}"
VERSION="${VERSION:-1.0.1}"
ORIGIN="${ORIGIN:-net/pfSense-pkg-mihomo}"
COMMENT="${COMMENT:-Mihomo proxy integration for pfSense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/MetaCubeX/mihomo}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-universal}}"
PKG_CREATE_FLAGS="${PKG_CREATE_FLAGS:--e}"
unset ABI || true
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"
MIHOMO_ASSET="${MIHOMO_ASSET:-clash-meta-freebsd-amd64.xz}"
MIHOMO_DOWNLOAD_URL="${MIHOMO_DOWNLOAD_URL:-https://github.com/Vincent-Loeng/clash-meta/releases/latest/download/$MIHOMO_ASSET}"
DOWNLOAD_TIMEOUT="${DOWNLOAD_TIMEOUT:-300}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-"$SCRIPT_DIR/dist"}"
DOWNLOADDIR="$WORKDIR/downloads"

die() {
    echo "error: $*" >&2
    exit 1
}

need_file() {
    [ -e "$SCRIPT_DIR/$1" ] || die "missing required file: $1"
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found. Run this script on FreeBSD/pfSense."
command -v tar >/dev/null 2>&1 || die "tar command not found."
command -v xz >/dev/null 2>&1 || die "xz command not found."
if ! command -v fetch >/dev/null 2>&1 && ! command -v curl >/dev/null 2>&1; then
    die "fetch or curl command not found."
fi

need_file "src/etc/rc.conf.d/mihomo"
need_file "src/usr/bin/mihomo_sub"
need_file "src/usr/local/etc/rc.d/mihomo"
need_file "src/usr/local/etc/mihomo/config.yaml"
need_file "src/usr/local/etc/mihomo/GeoIP.dat"
need_file "src/usr/local/etc/mihomo/GeoSite.dat"
need_file "src/usr/local/etc/mihomo/sub/sub.sh"
need_file "src/usr/local/etc/mihomo/sub/env"
need_file "src/usr/local/etc/mihomo/sub/template_config.yaml"
need_file "src/usr/local/pkg/mihomo.xml"
need_file "src/usr/local/pkg/mihomo.inc"
need_file "src/usr/local/share/pfSense-pkg-mihomo/info.xml"
need_file "src/usr/local/www/mihomo.php"
need_file "packaging/freebsd/+MANIFEST.in"
need_file "packaging/freebsd/+POST_INSTALL"
need_file "packaging/freebsd/+PRE_DEINSTALL"
need_file "packaging/freebsd/+POST_DEINSTALL"
need_file "packaging/freebsd/pkg-descr"

case "$TARGET_ABI" in
    universal)
        PKG_ABI="FreeBSD:*:amd64"
        PKG_ARCH="freebsd:*:x86:64"
        ;;
    native)
        PKG_ABI="$(pkg config ABI)"
        case "$PKG_ABI" in
            FreeBSD:*:amd64) ;;
            *) die "unsupported native ABI: $PKG_ABI" ;;
        esac
        ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
        PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"
        ;;
    FreeBSD:*:amd64)
        PKG_ABI="$TARGET_ABI"
        ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
        PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"
        ;;
    *)
        die "unsupported ABI: $TARGET_ABI"
        ;;
esac

rm -rf "$WORKDIR"
mkdir -p \
    "$STAGEDIR/usr/local/bin" \
    "$METADIR" \
    "$DISTDIR"

copy_tree() {
    src="$1"
    dst="$2"
    mkdir -p "$dst"
    (cd "$src" && tar --exclude '.DS_Store' --exclude '._*' -cf - .) | (cd "$dst" && tar -xf -)
}

download_file() {
    download_url="$1"
    download_dst="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fL --retry 3 --retry-all-errors --retry-delay 2 --connect-timeout 30 --max-time "$DOWNLOAD_TIMEOUT" -o "$download_dst" "$download_url"
    else
        fetch -T "$DOWNLOAD_TIMEOUT" -q -o "$download_dst" "$download_url"
    fi
}

unpack_binary() {
    archive="$1"
    binary_dst="$2"
    tmp="$binary_dst.tmp"

    rm -f "$tmp" "$binary_dst"
    if xz -t "$archive" >/dev/null 2>&1; then
        xz -dc "$archive" > "$tmp"
    else
        cp "$archive" "$tmp"
    fi
    mv -f "$tmp" "$binary_dst"
    chmod 0755 "$binary_dst"
    [ -s "$binary_dst" ] || die "binary is empty: $archive"
}

prepare_binary() {
    asset="$1"
    binary_url="$2"
    binary_dst="$3"
    local_asset="$SCRIPT_DIR/src/usr/local/bin/$asset"
    archive="$binary_dst.download"

    mkdir -p "$DOWNLOADDIR"
    if [ -f "$local_asset" ]; then
        echo "==> Using local asset $local_asset"
        unpack_binary "$local_asset" "$binary_dst"
    else
        echo "==> Downloading $binary_url"
        rm -f "$archive"
        download_file "$binary_url" "$archive"
        unpack_binary "$archive" "$binary_dst"
    fi
}

echo "==> Staging files"
copy_tree "$SCRIPT_DIR/src/etc" "$STAGEDIR/etc"
copy_tree "$SCRIPT_DIR/src/usr" "$STAGEDIR/usr"
prepare_binary "$MIHOMO_ASSET" "$MIHOMO_DOWNLOAD_URL" "$DOWNLOADDIR/mihomo"
install -m 0755 "$DOWNLOADDIR/mihomo" "$STAGEDIR/usr/local/bin/mihomo"
rm -f "$STAGEDIR/usr/local/bin/$MIHOMO_ASSET"

chmod 0644 "$STAGEDIR/etc/rc.conf.d/mihomo"
chmod 0755 "$STAGEDIR/usr/bin/mihomo_sub"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/mihomo"
chmod 0755 "$STAGEDIR/usr/local/etc/mihomo/sub/sub.sh"

echo "==> Generating plist"
find "$STAGEDIR" -type f | sed "s#^$STAGEDIR##" | sort > "$PLIST"

FLATSIZE=0
while IFS= read -r file; do
    size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"
    FLATSIZE=$((FLATSIZE + size))
done < "$PLIST"

echo "==> Generating metadata"
sed \
    -e "s#@PKG_NAME@#$PKG_NAME#g" \
    -e "s#@ORIGIN@#$ORIGIN#g" \
    -e "s#@VERSION@#$VERSION#g" \
    -e "s#@COMMENT@#$COMMENT#g" \
    -e "s#@MAINTAINER@#$MAINTAINER#g" \
    -e "s#@WWW@#$WWW#g" \
    -e "s#@ABI@#$PKG_ABI#g" \
    -e "s#@ARCH@#$PKG_ARCH#g" \
    -e "s#@PREFIX@#$PREFIX#g" \
    -e "s#@FLATSIZE@#$FLATSIZE#g" \
    -e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" \
    -e "/@DESC@/d" \
    "$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"

install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_INSTALL" "$METADIR/+POST_INSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+PRE_DEINSTALL" "$METADIR/+PRE_DEINSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_DEINSTALL" "$METADIR/+POST_DEINSTALL"

echo "==> Creating package for $PKG_ABI"
pkg create $PKG_CREATE_FLAGS -f "$FORMAT" -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"

CREATED="$DISTDIR/$PKG_NAME-$VERSION.pkg"
if [ -f "$CREATED" ] && [ "$(basename "$CREATED")" != "$OUTPUT_NAME" ]; then
    mv -f "$CREATED" "$DISTDIR/$OUTPUT_NAME"
fi

echo "==> Package: $DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME" >/dev/null
echo "==> Verified package metadata"
if command -v sha256 >/dev/null 2>&1; then
    sha256 "$DISTDIR/$OUTPUT_NAME"
fi
