<?php
/*
 * lucky.php
 * Lucky for pfSense.
 */

$allowautocomplete = true;
$pgtitle = array(gettext("Services"), gettext("Lucky"));
require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("/usr/local/pkg/lucky.inc");

if ($_POST) {
	if (isset($_POST['start'])) {
		lucky_start();
	} elseif (isset($_POST['stop'])) {
		lucky_stop();
	} elseif (isset($_POST['restart'])) {
		lucky_restart();
	} elseif (isset($_POST['save'])) {
		$lucky_config = [
			'enable' => isset($_POST['enable']) ? 'yes' : 'no',
			'conf_dir' => $_POST['conf_dir'] ?: '/usr/local/etc/lucky',
			'extra_args' => $_POST['extra_args'] ?? '',
			'web_port' => is_numeric($_POST['web_port'] ?? '') ? (string)(int)$_POST['web_port'] : '16601',
		];
		config_set_path('installedpackages/lucky/config/0', $lucky_config);
		write_config('Update Lucky package settings');
		lucky_resync_config();
		if ($lucky_config['enable'] === 'yes') {
			lucky_restart();
		} else {
			lucky_stop();
		}
	}
	header("Location: lucky.php");
	exit;
}

$lucky_config = lucky_get_config();
$running = lucky_is_running();
$request_host = $_SERVER['HTTP_HOST'] ?? '';
$request_host = preg_replace('/:\d+$/', '', $request_host);
$web_port = isset($lucky_config['web_port']) && is_numeric($lucky_config['web_port']) ? (int)$lucky_config['web_port'] : 16601;
$lucky_url = "http://{$request_host}:{$web_port}/";

include("head.inc");
?>
<style>
.lucky-panel .panel-heading {
	background: #3f3f3f;
	border-color: #3f3f3f;
	color: #fff;
	font-weight: 700;
	padding: 7px 14px;
}
.lucky-status {
	align-items: center;
	display: inline-flex;
	height: 24px;
	justify-content: center;
	line-height: 1;
	min-width: 72px;
	padding: 0 10px;
	text-align: center;
	vertical-align: middle;
}
.lucky-section-title {
	background: #3f3f3f;
	color: #fff;
	font-weight: 700;
	padding: 7px 14px;
}
.lucky-section-body {
	background: #fff;
	border-bottom: 1px solid #ddd;
	padding: 10px 14px;
}
.lucky-service-table {
	margin-bottom: 0;
}
.lucky-service-table td {
	padding-left: 14px !important;
	vertical-align: middle !important;
}
.lucky-service-table td:first-child {
	font-weight: 700;
	width: 140px;
}
.lucky-actions .btn {
	margin-right: 5px;
}
</style>

<div class="panel panel-default lucky-panel">
	<div class="lucky-section-title"><?=gettext("General Settings")?></div>
	<table class="table table-striped table-condensed lucky-service-table">
		<tr>
			<td><?=gettext("Service Status")?></td>
			<td>
				<span class="lucky-status label <?= $running ? 'label-success' : 'label-default' ?>">
					<?= $running ? gettext("Running") : gettext("Stopped") ?>
				</span>
			</td>
		</tr>
		<tr>
			<td><?=gettext("Access Address")?></td>
			<td>
				<a href="<?=htmlspecialchars($lucky_url)?>" target="_blank"><?=htmlspecialchars($lucky_url)?></a>
			</td>
		</tr>
		<tr>
			<td><?=gettext("Service Control")?></td>
			<td>
				<form method="post" class="form-inline lucky-actions">
					<button class="btn btn-success btn-sm" type="submit" name="start" <?=$running ? 'disabled' : ''?>><?=gettext("Start")?></button>
					<button class="btn btn-danger btn-sm" type="submit" name="stop" <?=$running ? '' : 'disabled'?>><?=gettext("Stop")?></button>
					<button class="btn btn-warning btn-sm" type="submit" name="restart"><?=gettext("Restart")?></button>
				</form>
			</td>
		</tr>
	</table>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><?=gettext("Advanced Settings")?></div>
	<div class="panel-body">
		<form method="post" class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-2 control-label"><?=gettext("Enable")?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="enable" value="yes" <?=($lucky_config['enable'] ?? 'yes') !== 'no' ? 'checked' : ''?>>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?=gettext("Config")?></label>
				<div class="col-sm-10">
					<input class="form-control" name="conf_dir" value="<?=htmlspecialchars($lucky_config['conf_dir'])?>">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?=gettext("Port")?></label>
				<div class="col-sm-10">
					<input class="form-control" name="web_port" value="<?=htmlspecialchars((string)$web_port)?>">
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button class="btn btn-primary" type="submit" name="save"><?=gettext("Save")?></button>
				</div>
			</div>
		</form>
	</div>
</div>

<?php include("foot.inc"); ?>
