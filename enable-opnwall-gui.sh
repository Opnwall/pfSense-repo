#!/bin/sh
set -eu

target="/etc/inc/pkg-utils.inc"
backup="${target}.opnwall.bak"
installed_page="/usr/local/www/pkg_mgr_installed.php"
installed_page_backup="${installed_page}.opnwall.bak"
changed=0

if php -r '
$f = "/etc/inc/pkg-utils.inc";
$oldAvailable = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiLXIgeyRnWydwcm9kdWN0X25hbWUnXX0iOwoJfQ==");
$newAvailable = base64_decode("aWYgKCRiYXNlX3BhY2thZ2VzKSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0gZWxzZSB7CgkJJHJlcG9fcGFyYW0gPSAiIjsKCX0=");
$oldInstalled = base64_decode("CQkJaWYgKCEkYmFzZV9wYWNrYWdlcyAmJgoJCQkgICAgcnRyaW0oJG91dCkgIT0gZ19nZXQoJ3Byb2R1Y3RfbmFtZScpKSB7CgkJCQljb250aW51ZTsKCQkJfQo=");
$newInstalled = base64_decode("CQkJLyogT3Bud2FsbDogYWxsb3cgaW5zdGFsbGVkIHBhY2thZ2VzIGZyb20gYWxsIGVuYWJsZWQgcmVwb3NpdG9yaWVzLiAqLwo=");
$webFile = "/usr/local/www/pkg_mgr_installed.php";
$oldCategories = base64_decode("aW1wbG9kZSgiICIsICRwa2dbJ2NhdGVnb3JpZXMnXSk=");
$newCategories = base64_decode("aW1wbG9kZSgiICIsIChhcnJheSkoJHBrZ1snY2F0ZWdvcmllcyddID8/IGFycmF5KCkpKQ==");
$s = file_get_contents($f);
if ($s === false) {
    fwrite(STDERR, "Unable to read $f.\n");
    exit(1);
}
$pkgChanges = 0;
if (substr_count($s, $oldAvailable) === 1) {
    $s = str_replace($oldAvailable, $newAvailable, $s);
    $pkgChanges++;
} elseif (strpos($s, $newAvailable) === false) {
    fwrite(STDERR, "Supported available-package query block was not found; no changes made.\n");
    exit(1);
}

if (substr_count($s, $oldInstalled) === 1) {
    $s = str_replace($oldInstalled, $newInstalled, $s);
    $pkgChanges++;
} elseif (strpos($s, $newInstalled) === false) {
    fwrite(STDERR, "Supported installed-package repository filter was not found; no changes made.\n");
    exit(1);
}

$web = file_get_contents($webFile);
if ($web === false) {
    fwrite(STDERR, "Unable to read $webFile.\n");
    exit(1);
}
$webChanges = 0;
if (substr_count($web, $oldCategories) === 1) {
    $web = str_replace($oldCategories, $newCategories, $web);
    $webChanges++;
} elseif (strpos($web, $newCategories) === false) {
    fwrite(STDERR, "Supported installed-package category rendering code was not found; no changes made.\n");
    exit(1);
}

if ($pkgChanges === 0 && $webChanges === 0) {
    echo "Opnwall GUI repository patch is already enabled.\n";
    exit(10);
}
if ($pkgChanges > 0 && !file_exists($f . ".opnwall.bak") && !copy($f, $f . ".opnwall.bak")) {
    fwrite(STDERR, "Unable to create backup.\n");
    exit(1);
}
if ($webChanges > 0 && !file_exists($webFile . ".opnwall.bak") && !copy($webFile, $webFile . ".opnwall.bak")) {
    fwrite(STDERR, "Unable to create WebGUI backup.\n");
    exit(1);
}
if ($pkgChanges > 0 && file_put_contents($f, $s) === false) {
    fwrite(STDERR, "Unable to write patched file.\n");
    exit(1);
}
if ($webChanges > 0 && file_put_contents($webFile, $web) === false) {
    fwrite(STDERR, "Unable to write patched WebGUI file.\n");
    exit(1);
}
echo "Opnwall GUI repository patch applied.\n";
'; then
    changed=1
else
    rc=$?
    [ "$rc" -eq 10 ] || exit "$rc"
fi

if ! php -l "$target" >/dev/null || ! php -l "$installed_page" >/dev/null; then
    if [ "$changed" -eq 1 ] && [ -f "$backup" ]; then
        cp -p "$backup" "$target"
    fi
    if [ "$changed" -eq 1 ] && [ -f "$installed_page_backup" ]; then
        cp -p "$installed_page_backup" "$installed_page"
    fi
    echo "PHP validation failed; the original file was restored." >&2
    exit 1
fi

if [ -x /etc/rc.php-fpm_restart ]; then
    /etc/rc.php-fpm_restart >/dev/null 2>&1 || true
fi

echo "Done. Refresh System > Package Manager > Available Packages or Installed Packages."
echo "Backup: $backup"
echo "Backup: $installed_page_backup"
