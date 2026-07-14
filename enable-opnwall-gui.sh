#!/bin/sh
set -eu

target="/etc/inc/pkg-utils.inc"
backup="${target}.opnwall.bak"
changed=0

if php -r '
$f = "/etc/inc/pkg-utils.inc";
$old = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiLXIgeyRnWydwcm9kdWN0X25hbWUnXX0iOwoJfQ==");
$new = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0=");
$s = file_get_contents($f);
if ($s === false) {
    fwrite(STDERR, "Unable to read $f.\n");
    exit(1);
}
$count = substr_count($s, $old);
if ($count === 0) {
    if (strpos($s, $new) !== false) {
        echo "Opnwall GUI repository patch is already enabled.\n";
        exit(10);
    }
    fwrite(STDERR, "Supported pfSense package query block was not found; no changes made.\n");
    exit(1);
}
if ($count !== 1) {
    fwrite(STDERR, "Unexpected package query block count: $count; no changes made.\n");
    exit(1);
}
if (!copy($f, $f . ".opnwall.bak")) {
    fwrite(STDERR, "Unable to create backup.\n");
    exit(1);
}
if (file_put_contents($f, str_replace($old, $new, $s)) === false) {
    fwrite(STDERR, "Unable to write patched file.\n");
    exit(1);
}
echo "Opnwall GUI repository patch applied.\n";
'; then
    changed=1
else
    rc=$?
    [ "$rc" -eq 10 ] || exit "$rc"
fi

if ! php -l "$target" >/dev/null; then
    if [ "$changed" -eq 1 ] && [ -f "$backup" ]; then
        cp -p "$backup" "$target"
    fi
    echo "PHP validation failed; the original file was restored." >&2
    exit 1
fi

if [ -x /etc/rc.php-fpm_restart ]; then
    /etc/rc.php-fpm_restart >/dev/null 2>&1 || true
fi

echo "Done. Refresh System > Package Manager > Available Packages."
echo "Backup: $backup"
