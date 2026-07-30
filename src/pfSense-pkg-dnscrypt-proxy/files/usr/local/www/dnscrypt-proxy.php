<?php
require_once("guiconfig.inc");
require_once("/usr/local/pkg/dnscrypt-proxy-i18n.inc");

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
$language = dnscrypt_proxy_language();
$suffix = $language === 'en_US' ? '' : '.' . $language;

header('Location: /pkg_edit.php?xml=' . rawurlencode($base . $suffix . '.xml'));
exit;
