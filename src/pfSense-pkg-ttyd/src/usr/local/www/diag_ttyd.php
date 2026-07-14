<?php
/*
 * diag_ttyd.php
 * ttyd for pfSense.
 */

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("ttyd"));
require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("/usr/local/pkg/ttyd.inc");

$config = ttyd_get_config();

$port = isset($config['port']) && is_numeric($config['port']) ? (int)$config['port'] : 7681;
$listen = !empty($config['interface']) ? $config['interface'] : '0.0.0.0';
$ssh_target = '127.0.0.1:22';

if (!ttyd_is_running()) {
	ttyd_start();
}

$running = ttyd_is_running();
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);
$terminal_url = "https://{$host}:{$port}/";

include("head.inc");
?>
<style>
.ttyd-panel {
	margin-bottom: 14px;
}
.ttyd-panel .panel-heading {
	background: #3f3f3f;
	border-color: #3f3f3f;
	color: #fff;
	font-weight: 700;
	padding: 7px 14px;
}
.ttyd-panel .panel-body {
	font-size: 15px;
	line-height: 1.4;
	padding: 9px 14px 12px;
}
.ttyd-meta {
	margin-bottom: 0;
}
.ttyd-meta td:first-child {
	width: 170px;
	font-weight: 700;
}
.ttyd-meta td {
	font-size: 15px;
	vertical-align: middle !important;
}
.ttyd-terminal {
	width: 100%;
	height: 67vh;
	min-height: 600px;
	border: 1px solid #222;
	background: #111;
}
</style>

<div class="panel panel-default ttyd-panel">
	<div class="panel-heading">
		<?=gettext("ttyd")?>
	</div>
	<div class="panel-body">
		<?=gettext("This page opens a full interactive terminal through ttyd. The shell itself is entered through SSH, so pfSense SSH permissions and login policy still apply. The embedded terminal uses the pfSense WebGUI certificate.")?>
	</div>
	<table class="table table-striped table-condensed ttyd-meta">
		<tr>
			<td><?=gettext("Terminal URL")?></td>
			<td><a href="<?=htmlspecialchars($terminal_url)?>" target="_blank"><?=htmlspecialchars($terminal_url)?></a></td>
		</tr>
		<tr>
			<td><?=gettext("SSH target")?></td>
			<td><?=htmlspecialchars($ssh_target)?></td>
		</tr>
	</table>
</div>

<?php if ($running): ?>
<iframe class="ttyd-terminal" src="<?=htmlspecialchars($terminal_url)?>"></iframe>
<?php else: ?>
<div class="alert alert-warning">
	<?=gettext("The terminal service could not be started. Make sure ttyd is installed, the pfSense WebGUI certificate is available, and SSH is enabled in System > Advanced > Admin Access.")?>
</div>
<?php endif; ?>

<?php include("foot.inc"); ?>
