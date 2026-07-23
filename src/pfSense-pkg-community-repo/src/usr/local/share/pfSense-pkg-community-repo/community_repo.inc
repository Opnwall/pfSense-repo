<?php

/* Community package repository helpers for the package manager pages. */

const COMMUNITY_REPO_NAME = 'opnwall';
const COMMUNITY_REPO_CONF = '/usr/local/etc/pkg/repos/opnwall.conf';

function community_repo_url() {
	$version = is_readable('/etc/version') ? trim((string)file_get_contents('/etc/version')) : '';
	$edition = preg_match('/^[0-9]{2}\.[0-9]{2}(?:\.|-|$)/', $version) ? 'pfSense-Plus' : 'pfSense-CE';

	return 'https://opnwall.github.io/pfSense-repo/repo/' . $edition . '/${ABI}';
}

function community_repo_text($key) {
	$translations = [
		'en' => [
			'tab' => 'Community Packages',
			'panel' => 'Community Repository',
			'address' => 'Repository URL',
			'enable' => 'Enable',
			'enable_label' => 'Enable community repository',
			'enable_help' => 'When enabled, the repository configuration is created automatically and the community catalog is downloaded or updated. No manual repository command is required.',
			'write_error' => 'Unable to update the community repository configuration.',
			'update_error' => 'The community repository was enabled, but its catalog could not be downloaded or updated.',
			'enabled' => 'The community repository has been enabled and its catalog was updated.',
			'disabled' => 'The community repository has been disabled.',
			'disabled_help' => 'The community repository is disabled. Enable it above to download and display community packages.',
		],
		'zh_CN' => [
			'tab' => '社区插件',
			'panel' => '社区仓库',
			'address' => '仓库地址',
			'enable' => '启用',
			'enable_label' => '启用社区仓库',
			'enable_help' => '勾选启用后将自动创建仓库配置，并下载或更新社区仓库索引，无需手动执行添加仓库命令。',
			'write_error' => '无法更新社区仓库配置。',
			'update_error' => '社区仓库已启用，但无法下载或更新仓库索引。',
			'enabled' => '社区仓库已启用，仓库索引已更新。',
			'disabled' => '社区仓库已禁用。',
			'disabled_help' => '社区仓库已禁用。请先启用仓库，才能下载并显示社区插件。',
		],
		'zh_TW' => [
			'tab' => '社群套件',
			'panel' => '社群套件庫',
			'address' => '套件庫位址',
			'enable' => '啟用',
			'enable_label' => '啟用社群套件庫',
			'enable_help' => '勾選啟用後將自動建立套件庫設定，並下載或更新社群套件庫索引，無需手動執行新增套件庫命令。',
			'write_error' => '無法更新社群套件庫設定。',
			'update_error' => '社群套件庫已啟用，但無法下載或更新套件庫索引。',
			'enabled' => '社群套件庫已啟用，套件庫索引已更新。',
			'disabled' => '社群套件庫已停用。',
			'disabled_help' => '社群套件庫已停用。請先啟用套件庫，才能下載並顯示社群套件。',
		],
	];
	if (function_exists('config_get_path')) {
		$language = (string)config_get_path('system/language', 'en');
	} else {
		$language = function_exists('g_get') ? (string)g_get('language') : 'en';
	}
	if (in_array($language, ['zh_CN', 'zh_Hans_CN'], true)) {
		$language = 'zh_CN';
	} elseif (in_array($language, ['zh_TW', 'zh_Hant_TW'], true)) {
		$language = 'zh_TW';
	} else {
		$language = 'en';
	}
	/* A selected Chinese locale can remain in config after its translation
	 * catalog is removed. Follow the language actually rendered by gettext. */
	if ($language !== 'en' && gettext('System') === 'System') {
		$language = 'en';
	}

	return $translations[$language][$key] ?? $translations['en'][$key] ?? $key;
}

function community_repo_enabled() {
	$config = @file_get_contents(COMMUNITY_REPO_CONF);
	return is_string($config) && preg_match('/enabled\s*:\s*yes\b/i', $config);
}

function community_repo_write_config($enabled) {
	$config = COMMUNITY_REPO_NAME . ": {\n" .
	    '  url: "' . community_repo_url() . "\",\n" .
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
