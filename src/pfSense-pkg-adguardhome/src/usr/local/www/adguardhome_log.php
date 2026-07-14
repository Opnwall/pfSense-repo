<?php
require_once("guiconfig.inc");

$log_file = "/var/log/adguardhome.log";

header('Content-Type: text/plain; charset=UTF-8');

if (!file_exists($log_file)) {
    echo gettext("No log file found.") . "\n";
    exit;
}

$lines = [];
exec('/usr/bin/tail -n 200 ' . escapeshellarg($log_file) . ' 2>&1', $lines);
echo implode("\n", $lines);
echo "\n";
