#!/bin/sh
set -eu

target="/etc/inc/pkg-utils.inc"
backup="${target}.opnwall.bak"
changed=0

if php -r '
$f = "/etc/inc/pkg-utils.inc";
$oldAvailable = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiLXIgeyRnWydwcm9kdWN0X25hbWUnXX0iOwoJfQ==");
$newAvailable = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0=");
$oldInstalled = base64_decode("CQkJaWYgKCEkYmFzZV9wYWNrYWdlcyAmJgoJCQkgICAgcnRyaW0oJG91dCkgIT0gZ19nZXQoJ3Byb2R1Y3RfbmFtZScpKSB7CgkJCQljb250aW51ZTsKCQkJfQo=");
$newInstalled = base64_decode("CQkJLyogT3Bud2FsbDogYWxsb3cgaW5zdGFsbGVkIHBhY2thZ2VzIGZyb20gYWxsIGVuYWJsZWQgcmVwb3NpdG9yaWVzLiAqLwo=");
$s = file_get_contents($f);
if ($s === false) {
    fwrite(STDERR, "Unable to read $f.\n");
    exit(1);
}
$changes = 0;
if (substr_count($s, $oldAvailable) === 1) {
    $s = str_replace($oldAvailable, $newAvailable, $s);
    $changes++;
} elseif (strpos($s, $newAvailable) === false) {
    fwrite(STDERR, "Supported available-package query block was not found; no changes made.\n");
    exit(1);
}

if (substr_count($s, $oldInstalled) === 1) {
    $s = str_replace($oldInstalled, $newInstalled, $s);
    $changes++;
} elseif (strpos($s, $newInstalled) === false) {
    fwrite(STDERR, "Supported installed-package repository filter was not found; no changes made.\n");
    exit(1);
}

if ($changes === 0) {
    echo "Opnwall GUI repository patch is already enabled.\n";
    exit(10);
}
if (!file_exists($f . ".opnwall.bak") && !copy($f, $f . ".opnwall.bak")) {
    fwrite(STDERR, "Unable to create backup.\n");
    exit(1);
}
if (file_put_contents($f, $s) === false) {
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

echo "Done. Refresh System > Package Manager > Available Packages or Installed Packages."
echo "Backup: $backup"
