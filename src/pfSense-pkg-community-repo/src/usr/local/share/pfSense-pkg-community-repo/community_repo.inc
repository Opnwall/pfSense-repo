<?php

/* Community package repository helpers for the package manager pages. */

const COMMUNITY_REPO_NAME = 'opnwall';
const COMMUNITY_REPO_CONF = '/usr/local/etc/pkg/repos/opnwall.conf';
const COMMUNITY_REPO_URL = 'https://opnwall.github.io/pfSense-repo/repo/pfSense-CE/${ABI}';

function community_repo_enabled() {
	$config = @file_get_contents(COMMUNITY_REPO_CONF);
	return is_string($config) && preg_match('/enabled\s*:\s*yes\b/i', $config);
}

function community_repo_write_config($enabled) {
	$config = COMMUNITY_REPO_NAME . ": {\n" .
	    '  url: "' . COMMUNITY_REPO_URL . "\",\n" .
	    '  enabled: ' . ($enabled ? 'yes' : 'no') . ",\n" .
	    "  priority: 10\n}\n";
	$tmpfile = COMMUNITY_REPO_CONF . '.tmp';

	if (file_put_contents($tmpfile, $config, LOCK_EX) === false ||
	    !chmod($tmpfile, 0644) || !rename($tmpfile, COMMUNITY_REPO_CONF)) {
		@unlink($tmpfile);
		return false;
	}

	return true;
}

function community_repo_package_names() {
	$names = array();
	$output = array();
	$status = 0;
	exec('/usr/sbin/pkg rquery -r ' . escapeshellarg(COMMUNITY_REPO_NAME) .
	    " '%n' 2>/dev/null", $output, $status);
	if ($status === 0) {
		foreach ($output as $name) {
			$name = trim($name);
			if (str_starts_with($name, 'pfSense-pkg-')) {
				$names[$name] = true;
			}
		}
	}

	return $names;
}

