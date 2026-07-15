<?php

require_once('guiconfig.inc');
require_once('/usr/local/pkg/lanspeedtest.inc');

function lantest_system_language(): string {
	$language = (string)config_get_path('system/language', 'en');
	$language = strtolower(str_replace('-', '_', trim($language)));
	if (in_array($language, ['zh_cn', 'zh_hans_cn'], true)) {
		return 'zh_CN';
	}
	if (in_array($language, ['zh_tw', 'zh_hant_tw'], true)) {
		return 'zh_TW';
	}
	return 'en';
}

function lantest_t(string $key): string {
	static $translations = [
		'en' => [
			'diagnostics' => 'Diagnostics', 'service_status' => 'Service Status', 'running' => 'Running',
			'stopped' => 'Stopped', 'access_address' => 'Access Address', 'service_control' => 'Service Control',
			'start' => 'Start', 'stop' => 'Stop', 'restart' => 'Restart', 'settings' => 'Settings',
			'enable' => 'Enable', 'listen_interface' => 'Listen Interface', 'interface_help' => 'Choose an internal interface. The service listens only on its IPv4 address.',
			'port' => 'Port', 'save' => 'Save', 'port_error' => 'Port must be between 1024 and 65535.',
			'interface_error' => 'Select a configured interface.',
		],
		'zh_CN' => [
			'diagnostics' => '诊断', 'service_status' => '服务状态', 'running' => '运行',
			'stopped' => '停止', 'access_address' => '访问地址', 'service_control' => '服务控制',
			'start' => '启动', 'stop' => '停止', 'restart' => '重启', 'settings' => '设置',
			'enable' => '启用', 'listen_interface' => '监听接口', 'interface_help' => '请选择内部接口，服务仅监听该接口的 IPv4 地址。',
			'port' => '端口', 'save' => '保存', 'port_error' => '端口必须在 1024 到 65535 之间。',
			'interface_error' => '请选择已配置的接口。',
		],
		'zh_TW' => [
			'diagnostics' => '診斷', 'service_status' => '服務狀態', 'running' => '執行中',
			'stopped' => '已停止', 'access_address' => '存取位址', 'service_control' => '服務控制',
			'start' => '啟動', 'stop' => '停止', 'restart' => '重新啟動', 'settings' => '設定',
			'enable' => '啟用', 'listen_interface' => '監聽介面', 'interface_help' => '請選擇內部介面，服務僅監聽該介面的 IPv4 位址。',
			'port' => '連接埠', 'save' => '儲存', 'port_error' => '連接埠必須介於 1024 到 65535 之間。',
			'interface_error' => '請選擇已設定的介面。',
		],
	];
	$language = lantest_system_language();
	return $translations[$language][$key] ?? $translations['en'][$key] ?? $key;
}

$pgtitle = [lantest_t('diagnostics'), 'LanTest'];

if ($_POST) {
	if (isset($_POST['start'])) {
		lanspeedtest_start();
	} elseif (isset($_POST['stop'])) {
		lanspeedtest_stop();
	} elseif (isset($_POST['restart'])) {
		lanspeedtest_restart();
	} elseif (isset($_POST['save'])) {
		$port = filter_var($_POST['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
		$interfaces = get_configured_interface_with_descr();
		$interface = (string)($_POST['interface'] ?? 'lan');
		if ($port === false) {
			$input_errors[] = lantest_t('port_error');
		}
		if (!array_key_exists($interface, $interfaces)) {
			$input_errors[] = lantest_t('interface_error');
		}
		if (empty($input_errors)) {
			$settings = ['enable' => isset($_POST['enable']) ? 'yes' : 'no', 'interface' => $interface, 'port' => (string)$port];
			config_set_path('installedpackages/lanspeedtest/config/0', $settings);
			write_config('Update LanTest settings');
			lanspeedtest_resync_config();
			$settings['enable'] === 'yes' ? lanspeedtest_restart() : lanspeedtest_stop();
		}
	}
	if (empty($input_errors)) {
		header('Location: lanspeedtest.php');
		exit;
	}
}

$settings = lanspeedtest_get_config();
$interfaces = get_configured_interface_with_descr();
$address = lanspeedtest_interface_address((string)$settings['interface']);
$port = (int)$settings['port'];
$url = "http://{$address}:{$port}/";
$running = lanspeedtest_is_running();

include('head.inc');
if (!empty($input_errors)) {
	print_input_errors($input_errors);
}
?>
<style>
.lantest-status-table th {
	padding-left: 15px !important;
}
</style>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title"><?=gettext('LanTest')?></h2></div>
  <table class="table table-striped table-condensed lantest-status-table">
    <tbody>
      <tr><th style="width:180px"><?=lantest_t('service_status')?></th><td><span class="label <?=$running ? 'label-success' : 'label-default'?>"><?=$running ? lantest_t('running') : lantest_t('stopped')?></span></td></tr>
      <tr><th><?=lantest_t('access_address')?></th><td><a href="<?=htmlspecialchars($url)?>" target="_blank" rel="noopener"><?=htmlspecialchars($url)?></a></td></tr>
      <tr><th><?=lantest_t('service_control')?></th><td>
        <form method="post" class="form-inline">
          <button class="btn btn-success btn-sm" name="start" <?=$running ? 'disabled' : ''?>><i class="fa fa-play icon-embed-btn"></i><?=lantest_t('start')?></button>
          <button class="btn btn-danger btn-sm" name="stop" <?=$running ? '' : 'disabled'?>><i class="fa fa-stop icon-embed-btn"></i><?=lantest_t('stop')?></button>
          <button class="btn btn-warning btn-sm" name="restart"><i class="fa fa-refresh icon-embed-btn"></i><?=lantest_t('restart')?></button>
        </form>
      </td></tr>
    </tbody>
  </table>
</div>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title"><?=lantest_t('settings')?></h2></div>
  <div class="panel-body">
    <form method="post" class="form-horizontal">
      <div class="form-group"><label class="col-sm-2 control-label"><?=lantest_t('enable')?></label><div class="col-sm-10"><input type="checkbox" name="enable" value="yes" <?=$settings['enable'] === 'yes' ? 'checked' : ''?>></div></div>
      <div class="form-group"><label class="col-sm-2 control-label"><?=lantest_t('listen_interface')?></label><div class="col-sm-10"><select class="form-control" name="interface">
        <?php foreach ($interfaces as $ifname => $description): ?><option value="<?=htmlspecialchars($ifname)?>" <?=$settings['interface'] === $ifname ? 'selected' : ''?>><?=htmlspecialchars($description)?> (<?=htmlspecialchars($ifname)?>)</option><?php endforeach; ?>
      </select><span class="help-block"><?=lantest_t('interface_help')?></span></div></div>
      <div class="form-group"><label class="col-sm-2 control-label"><?=lantest_t('port')?></label><div class="col-sm-10"><input class="form-control" type="number" min="1024" max="65535" name="port" value="<?=htmlspecialchars((string)$port)?>"></div></div>
      <div class="form-group"><div class="col-sm-offset-2 col-sm-10"><button class="btn btn-primary" name="save"><i class="fa fa-save icon-embed-btn"></i><?=lantest_t('save')?></button></div></div>
    </form>
  </div>
</div>
<?php include('foot.inc'); ?>
