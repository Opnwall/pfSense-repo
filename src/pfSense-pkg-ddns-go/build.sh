#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-pfSense-pkg-ddns-go}"
VERSION="${VERSION:-1.0.1}"
ORIGIN="${ORIGIN:-dns/pfSense-pkg-ddns-go}"
COMMENT="${COMMENT:-ddns-go dynamic DNS integration for pfSense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/jeessy2/ddns-go}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-universal}}"
PKG_CREATE_FLAGS="${PKG_CREATE_FLAGS:--e}"
unset ABI || true
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-"$SCRIPT_DIR/dist"}"
DOWNLOADDIR="$WORKDIR/downloads"
DDNSGO_GITHUB_API="${DDNSGO_GITHUB_API:-https://api.github.com/repos/jeessy2/ddns-go/releases/latest}"
DOWNLOAD_TIMEOUT="${DOWNLOAD_TIMEOUT:-300}"

die() {
	echo "error: $*" >&2
	exit 1
}

need_file() {
	[ -e "$SCRIPT_DIR/$1" ] || die "missing required file: $1"
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found. Run this script on FreeBSD/pfSense."
command -v tar >/dev/null 2>&1 || die "tar command not found."
if ! command -v fetch >/dev/null 2>&1 && ! command -v curl >/dev/null 2>&1; then
	die "fetch or curl command not found."
fi

need_file "src/etc/rc.conf.d/ddnsgo"
need_file "src/usr/local/etc/rc.d/ddnsgo"
need_file "src/usr/local/etc/rc.d/ddnsgo.sh"
need_file "src/usr/local/pkg/ddnsgo.inc"
need_file "src/usr/local/pkg/ddnsgo.xml"
need_file "src/usr/local/share/pfSense/menu/pfSense-Services_ddnsgo.xml"
need_file "src/usr/local/share/pfSense-pkg-ddns-go/config.yaml.sample"
need_file "src/usr/local/share/pfSense-pkg-ddns-go/info.xml"
need_file "src/usr/local/www/services_ddnsgo.php"
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
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR" "$DOWNLOADDIR"

copy_tree() {
	src="$1"
	dst="$2"
	mkdir -p "$dst"
	(cd "$src" && tar --exclude '.DS_Store' -cf - .) | (cd "$dst" && tar -xf -)
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

get_latest_freebsd_amd64_url() {
	latest_json="$DOWNLOADDIR/ddns-go-latest.json"
	download_file "$DDNSGO_GITHUB_API" "$latest_json"
	sed -n 's/.*"browser_download_url"[[:space:]]*:[[:space:]]*"\([^"]*freebsd_x86_64\.tar\.gz\)".*/\1/p' "$latest_json" | head -1
}

prepare_ddnsgo_binary() {
	local_binary="$SCRIPT_DIR/src/usr/local/bin/ddns-go"
	binary_dst="$DOWNLOADDIR/ddns-go"

	if [ -f "$local_binary" ]; then
		echo "==> Using bundled ddns-go binary: src/usr/local/bin/ddns-go"
		cp -f "$local_binary" "$binary_dst"
	else
		echo "==> Resolving latest ddns-go FreeBSD amd64 release"
		download_url="$(get_latest_freebsd_amd64_url)"
		[ -n "$download_url" ] || die "could not resolve latest ddns-go FreeBSD amd64 asset"
		archive="$DOWNLOADDIR/$(basename "$download_url")"
		extract_dir="$DOWNLOADDIR/extract"

		echo "==> Downloading $download_url"
		download_file "$download_url" "$archive"
		rm -rf "$extract_dir"
		mkdir -p "$extract_dir"
		tar -xzf "$archive" -C "$extract_dir"
		found="$(find "$extract_dir" -type f -name ddns-go | head -1)"
		[ -n "$found" ] || die "ddns-go binary not found in $archive"
		cp -f "$found" "$binary_dst"
	fi

	chmod 0755 "$binary_dst"
	[ -s "$binary_dst" ] || die "ddns-go binary is empty"
}

echo "==> Staging files"
copy_tree "$SCRIPT_DIR/src/etc" "$STAGEDIR/etc"
copy_tree "$SCRIPT_DIR/src/usr" "$STAGEDIR/usr"
prepare_ddnsgo_binary
mkdir -p "$STAGEDIR/usr/local/bin"
install -m 0755 "$DOWNLOADDIR/ddns-go" "$STAGEDIR/usr/local/bin/ddns-go"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/ddnsgo"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/ddnsgo.sh"
chmod 0644 "$STAGEDIR/etc/rc.conf.d/ddnsgo"

echo "==> Generating plist"
find "$STAGEDIR" \( -type f -o -type l \) | sed "s#^$STAGEDIR##" | sort > "$PLIST"

FLATSIZE=0
while IFS= read -r file; do
	if [ -L "$STAGEDIR$file" ]; then
		size=0
	else
		size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"
	fi
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
