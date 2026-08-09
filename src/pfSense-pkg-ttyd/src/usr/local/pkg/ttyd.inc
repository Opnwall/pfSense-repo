<?php

require_once('service-utils.inc');

function ttyd_text($key, $language = null) {
	static $translations = [
		'zh_CN' => [
			'Diagnostics' => '诊断',
			'This page opens a full interactive terminal through ttyd. The shell itself is entered through SSH, so pfSense SSH permissions and login policy still apply. The embedded terminal uses the pfSense WebGUI certificate.' => '此页面通过 ttyd 打开完整的交互式终端。终端通过 SSH 登录，因此仍遵循 pfSense 的 SSH 权限和登录策略。嵌入式终端使用 pfSense WebGUI 证书。',
			'Terminal URL' => '终端地址',
			'SSH target' => 'SSH 目标',
			'The terminal service could not be started. Make sure ttyd is installed, the pfSense WebGUI certificate is available, and SSH is enabled in System > Advanced > Admin Access.' => '无法启动终端服务。请确认 ttyd 已安装、pfSense WebGUI 证书可用，并已在“系统 > 高级 > 管理访问”中启用 SSH。',
		],
		'zh_TW' => [
			'Diagnostics' => '診斷',
			'This page opens a full interactive terminal through ttyd. The shell itself is entered through SSH, so pfSense SSH permissions and login policy still apply. The embedded terminal uses the pfSense WebGUI certificate.' => '此頁面透過 ttyd 開啟完整的互動式終端機。終端機透過 SSH 登入，因此仍遵循 pfSense 的 SSH 權限與登入原則。嵌入式終端機使用 pfSense WebGUI 憑證。',
			'Terminal URL' => '終端機位址',
			'SSH target' => 'SSH 目標',
			'The terminal service could not be started. Make sure ttyd is installed, the pfSense WebGUI certificate is available, and SSH is enabled in System > Advanced > Admin Access.' => '無法啟動終端機服務。請確認 ttyd 已安裝、pfSense WebGUI 憑證可用，並已在「系統 > 進階 > 管理存取」中啟用 SSH。',
		],
	];

	if ($language === null) {
		$language = function_exists('config_get_path') ? (string)config_get_path('system/language', 'en') : 'en';
	}
	if (in_array($language, ['zh_CN', 'zh_Hans_CN'], true)) {
		$language = 'zh_CN';
	} elseif (in_array($language, ['zh_TW', 'zh_Hant_TW'], true)) {
		$language = 'zh_TW';
	} else {
		return $key;
	}

	return $translations[$language][$key] ?? $key;
}

function ttyd_get_config() {
	$defaults = [
		'interface' => '0.0.0.0',
		'port' => '7681',
		'base_path' => '/',
		'ssh_host' => '127.0.0.1',
		'ssh_port' => '22',
	];

	$config = config_get_path('installedpackages/ttyd/config/0', []);
	if (empty($config)) {
		$config = config_get_path('installedpackages/pfsense_ttyd/config/0', []);
	}
	if (!is_array($config)) {
		$config = [];
	}

	return array_merge($defaults, $config);
}

function ttyd_is_running() {
	$pidfile = '/var/run/ttyd.pid';
	if (!is_readable($pidfile)) {
		return false;
	}

	$pid = (int)trim(file_get_contents($pidfile));
	if ($pid <= 0) {
		return false;
	}

	if (function_exists('posix_kill')) {
		return posix_kill($pid, 0);
	}

	exec('/bin/kill -0 ' . escapeshellarg((string)$pid) . ' 2>/dev/null', $output, $rc);
	return $rc === 0;
}

function ttyd_wait_for_state($want_running, $timeout_usec = 2000000) {
	$deadline = microtime(true) + ($timeout_usec / 1000000);
	do {
		if (ttyd_is_running() === $want_running) {
			return true;
		}
		usleep(100000);
	} while (microtime(true) < $deadline);

	return false;
}

function ttyd_start() {
	mwexec('/usr/local/etc/rc.d/ttyd onestart');
	ttyd_wait_for_state(true);
}

function ttyd_stop() {
	mwexec('/usr/local/etc/rc.d/ttyd onestop');
	ttyd_wait_for_state(false);
}

function ttyd_rc_shell_quote($value) {
	return "'" . str_replace("'", "'\\''", $value) . "'";
}

function ttyd_resync_config() {
	conf_mount_rw();
	$config = ttyd_get_config();

	$port = isset($config['port']) && is_numeric($config['port']) ? (int)$config['port'] : 7681;
	$interface = !empty($config['interface']) ? $config['interface'] : '0.0.0.0';
	$base_path = !empty($config['base_path']) ? $config['base_path'] : '/';
	$ssh_host = '127.0.0.1';
	$ssh_port = 22;
	$ssh_command = 'printf "login: "; read -r ttyd_login; if [ -z "$ttyd_login" ]; then echo "login is required"; exit 1; fi; exec /usr/bin/ssh -tt -p ' . $ssh_port . ' -o PreferredAuthentications=keyboard-interactive,password -o PubkeyAuthentication=no -o ServerAliveInterval=30 -o StrictHostKeyChecking=accept-new "$ttyd_login@' . $ssh_host . '"';

	$contents = "";
	$contents .= "ttyd_enable=\"YES\"\n";
	$contents .= "ttyd_port=\"" . $port . "\"\n";
	$contents .= "ttyd_interface=\"" . addcslashes($interface, "\\\"") . "\"\n";
	$contents .= "ttyd_base_path=\"" . addcslashes($base_path, "\\\"") . "\"\n";
	$contents .= "ttyd_command=" . ttyd_rc_shell_quote($ssh_command) . "\n";

	file_put_contents('/etc/rc.conf.d/ttyd', $contents);
	conf_mount_ro();

	ttyd_start();
}

function ttyd_php_install_command() {
	ttyd_resync_config();
}

function ttyd_php_deinstall_command() {
	ttyd_stop();
	@unlink('/usr/local/etc/pfsense_ttyd.credential');
	@unlink('/usr/local/etc/pfsense_ttyd.crt');
	@unlink('/usr/local/etc/pfsense_ttyd.key');
	@unlink('/var/log/pfsense_ttyd.log');
	@unlink('/usr/local/etc/ttyd.crt');
	@unlink('/usr/local/etc/ttyd.key');
	@unlink('/var/log/ttyd.log');
}
