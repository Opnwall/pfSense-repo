<?php
require_once("guiconfig.inc");

$pages = [
	'general' => 'dnscrypt-proxy',
	'servers' => 'dnscrypt-proxy-servers',
	'cache' => 'dnscrypt-proxy-cache',
	'logging' => 'dnscrypt-proxy-logging',
	'lists' => 'dnscrypt-proxy-lists',
	'advanced' => 'dnscrypt-proxy-advanced',
];
$page = $_GET['page'] ?? 'general';
$base = $pages[$page] ?? $pages['general'];

header('Location: /pkg_edit.php?xml=' . rawurlencode($base . '.xml'));
exit;
