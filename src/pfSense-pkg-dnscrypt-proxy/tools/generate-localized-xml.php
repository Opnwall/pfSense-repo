#!/usr/bin/env php
<?php
/*
 * Generate the localized pfSense package XML files committed with the package.
 * Run from the repository root after changing an English XML file or a map.
 */
require_once(__DIR__ . '/../files/usr/local/pkg/dnscrypt-proxy-i18n.inc');

$directory = __DIR__ . '/../files/usr/local/pkg';
$files = glob($directory . '/dnscrypt-proxy*.xml');
$files = array_values(array_filter($files, function ($file) {
	return !preg_match('/\.(zh_CN|zh_TW)\.xml$/', $file);
}));

foreach (['zh_CN', 'zh_TW'] as $language) {
	$translations = dnscrypt_proxy_translations($language);
	uksort($translations, function ($a, $b) {
		return strlen($b) <=> strlen($a);
	});

	foreach ($files as $source) {
		$xml = file_get_contents($source);
		foreach ($translations as $english => $localized) {
			$xml = str_replace($english, $localized, $xml);
			$xml = str_replace(
				htmlspecialchars($english, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
				htmlspecialchars($localized, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
				$xml
			);
		}

		$xml = preg_replace(
			'#<url>/pkg_edit\.php\?xml=(dnscrypt-proxy(?:-[a-z]+)?)\.xml</url>#',
			'<url>/dnscrypt-proxy.php?page=${1}</url>',
			$xml
		);
		$page_names = [
			'dnscrypt-proxy' => 'general',
			'dnscrypt-proxy-servers' => 'servers',
			'dnscrypt-proxy-cache' => 'cache',
			'dnscrypt-proxy-logging' => 'logging',
			'dnscrypt-proxy-lists' => 'lists',
			'dnscrypt-proxy-advanced' => 'advanced',
		];
		foreach ($page_names as $name => $page) {
			$xml = str_replace(
				"<url>/dnscrypt-proxy.php?page={$name}</url>",
				"<url>/dnscrypt-proxy.php?page={$page}</url>",
				$xml
			);
		}
		$target = preg_replace('/\.xml$/', ".{$language}.xml", $source);
		file_put_contents($target, $xml);
		echo basename($target), PHP_EOL;
	}
}
