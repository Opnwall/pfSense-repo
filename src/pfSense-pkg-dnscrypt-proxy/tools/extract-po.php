#!/usr/bin/env php
<?php
/*
 * Extract the DNSCrypt Proxy GUI strings into pfSense PO fragments.
 *
 * The package uses the system "pfSense" gettext domain. These files are
 * intended to be merged into the corresponding pfSense translation PO files.
 */

require_once(__DIR__ . '/translation-map.php');

$root = dirname(__DIR__);
$catalogs = [
	'zh_CN' => dnscrypt_proxy_translations('zh_CN'),
	'zh_TW' => dnscrypt_proxy_translations('zh_TW'),
];
$messages = [];

function normalize_msgid($text) {
	return html_entity_decode(
		preg_replace('/\s+/', ' ', trim($text)),
		ENT_QUOTES | ENT_HTML5,
		'UTF-8'
	);
}

function add_message(&$messages, $msgid, $reference) {
	$msgid = normalize_msgid($msgid);
	if ($msgid === '') {
		return;
	}
	if (!isset($messages[$msgid])) {
		$messages[$msgid] = [];
	}
	$messages[$msgid][$reference] = true;
}

function po_quote($value) {
	return json_encode(
		$value,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
}

$php_files = array_merge(
	glob($root . '/files/usr/local/www/*.php'),
	glob($root . '/files/usr/local/pkg/*.inc')
);
foreach ($php_files as $file) {
	$content = file_get_contents($file);
	if (preg_match_all('/\bgettext\(\s*(["\'])(.*?)\1\s*\)/s', $content, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$msgid = stripcslashes($match[2]);
			$offset = strpos($content, $match[0]);
			$line = substr_count(substr($content, 0, $offset), "\n") + 1;
			add_message($messages, $msgid, substr($file, strlen($root) + 1) . ':' . $line);
		}
	}
}

$package_include = file_get_contents($root . '/files/usr/local/pkg/dnscrypt-proxy.inc');
if (preg_match_all('/^# ([^\r\n]+)$/m', $package_include, $matches)) {
	foreach ($matches[1] as $msgid) {
		if (isset($catalogs['zh_CN'][$msgid]) || isset($catalogs['zh_TW'][$msgid])) {
			add_message(
				$messages,
				$msgid,
				'files/usr/local/pkg/dnscrypt-proxy.inc'
			);
		}
	}
}

$xml_files = array_values(array_filter(
	glob($root . '/files/usr/local/pkg/dnscrypt-proxy*.xml'),
	function ($file) {
		return !preg_match('/\.(zh_CN|zh_TW)\.xml$/', $file);
	}
));
foreach ($xml_files as $file) {
	$document = new DOMDocument();
	if (!$document->load($file)) {
		throw new RuntimeException('Unable to parse ' . $file);
	}
	$xpath = new DOMXPath($document);
	foreach ($xpath->query('//text()[not(ancestor::copyright)]') as $node) {
		$parts = preg_split('/(<[^>]+>)/', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($parts as $part) {
			foreach (preg_split('/\r\n|\n|\r/', $part) as $line) {
				$msgid = normalize_msgid($line);
				if ($msgid !== '' &&
				    (isset($catalogs['zh_CN'][$msgid]) || isset($catalogs['zh_TW'][$msgid]))) {
					add_message(
						$messages,
						$msgid,
						substr($file, strlen($root) + 1) . ':' . $node->getLineNo()
					);
				}
			}
		}
	}
}

ksort($messages, SORT_NATURAL | SORT_FLAG_CASE);
$output_directory = $root . '/translations';
if (!is_dir($output_directory) && !mkdir($output_directory, 0755, true)) {
	throw new RuntimeException('Unable to create ' . $output_directory);
}

foreach ($catalogs as $language => $translations) {
	$plural_forms = $language === 'zh_TW'
		? 'nplurals=1; plural=0;'
		: 'nplurals=1; plural=0;';
	$output = <<<PO
# DNSCrypt Proxy translations for the pfSense gettext domain.
# Merge this fragment into pfSense.po for {$language}.
msgid ""
msgstr ""
"Project-Id-Version: pfSense-pkg-dnscrypt-proxy\\n"
"PO-Revision-Date: 2026-07-30 00:00+0800\\n"
"Last-Translator: Opnwall community\\n"
"Language-Team: Opnwall community\\n"
"Language: {$language}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: {$plural_forms}\\n"

PO;

	foreach ($messages as $msgid => $references) {
		if (!isset($translations[$msgid]) || $translations[$msgid] === $msgid) {
			continue;
		}
		$output .= '#: ' . implode(' ', array_keys($references)) . "\n";
		$output .= 'msgid ' . po_quote($msgid) . "\n";
		$output .= 'msgstr ' . po_quote($translations[$msgid]) . "\n\n";
	}

	$target = $output_directory . '/pfSense-dnscrypt-proxy.' . $language . '.po';
	$output = rtrim($output) . "\n";
	file_put_contents($target, $output);
	echo substr($target, strlen($root) + 1), "\n";
}
