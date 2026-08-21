<?php
/* EasyTier controller for pfSense. */

##|+PRIV
##|*IDENT=page-vpn-easytier
##|*NAME=VPN: EasyTier
##|*DESCR=Allow access to the EasyTier controller.
##|*MATCH=vpn_easytier.php*
##|-PRIV

require_once("guiconfig.inc");

bindtextdomain('pfSense-pkg-easytier', '/usr/local/share/locale');
bind_textdomain_codeset('pfSense-pkg-easytier', 'UTF-8');

function easytier_gettext($message) {
	$translated = dgettext('pfSense-pkg-easytier', $message);
	return ($translated === $message) ? \gettext($message) : $translated;
}

$pgtitle = array(easytier_gettext("VPN"), easytier_gettext("EasyTier"));
$service = "/usr/local/etc/rc.d/easytier";
$config_file = "/usr/local/etc/easytier/config.toml";
$log_file = "/var/log/easytier.log";
$savemsg = null;
$input_errors = array();
$command_pending = false;
$view = $_GET['view'] ?? 'status';
if (!in_array($view, array('status', 'peers', 'config', 'log'), true)) {
	$view = 'status';
}

function easytier_running() {
	global $service;
	return (mwexec($service . " status", true) === 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	if (in_array($action, array('save', 'save_restart'), true)) {
		$config_text = str_replace("\r\n", "\n", $_POST['config'] ?? '');
		if (strpos($config_text, "\0") !== false) {
			$input_errors[] = easytier_gettext("The configuration contains invalid data.");
		} else {
			if (!is_dir(dirname($config_file))) {
				mkdir(dirname($config_file), 0700, true);
			}
			file_put_contents($config_file, $config_text, LOCK_EX);
			chmod($config_file, 0600);
			write_config(easytier_gettext("Saved EasyTier configuration"));
			$savemsg = easytier_gettext("EasyTier configuration saved.");
			if ($action === 'save_restart') {
				mwexec_bg($service . " restart");
				$command_pending = true;
				$savemsg = easytier_gettext("Configuration saved. The restart was submitted in the background.");
			}
		}
	} elseif (in_array($action, array('start', 'stop', 'restart'), true)) {
		/* EasyTier startup may spend time probing interfaces and peers. Never hold
		 * the PHP-FPM request open while rc.d waits for that work to finish. */
		mwexec_bg($service . " " . $action);
		$command_pending = true;
		$savemsg = sprintf(easytier_gettext("EasyTier %s command submitted. The status will refresh automatically."), $action);
	}
}

$running = easytier_running();
$config_text = is_readable($config_file) ? file_get_contents($config_file) : '';
$version_output = array();
exec('/usr/local/sbin/easytier-core --version 2>/dev/null', $version_output);
$version = trim(implode(' ', $version_output));
$pid = '';
if ($running && is_readable('/var/run/easytier.pid')) {
	$pid = trim(file_get_contents('/var/run/easytier.pid'));
}

function easytier_config_value($config, $key) {
	if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*"([^"]*)"/m', $config, $matches)) {
		return $matches[1];
	}
	return '';
}

$node_name = easytier_config_value($config_text, 'hostname');
$virtual_ip = easytier_config_value($config_text, 'ipv4');
$network_name = easytier_config_value($config_text, 'network_name');
$peer_rows = array();
$peer_error = '';
if ($view === 'peers' && $running) {
	$peer_output = array();
	$peer_result = 0;
	exec('/bin/timeout 5 /usr/local/sbin/easytier-cli -p 127.0.0.1:15888 peer 2>&1', $peer_output, $peer_result);
	if ($peer_result === 0) {
		foreach ($peer_output as $line) {
			$line = trim($line);
			if (substr($line, 0, 1) !== '|' || strpos($line, '---') !== false || strpos($line, 'ipv4') !== false) {
				continue;
			}
			$columns = array_map('trim', explode('|', trim($line, '|')));
			if (count($columns) >= 10) {
				$peer_rows[] = array_slice($columns, 0, 10);
			}
		}
	} else {
		$peer_error = easytier_gettext("Unable to query EasyTier peers. Check that the RPC portal is 127.0.0.1:15888.");
	}
}
$log_text = '';
if (is_readable($log_file)) {
	$lines = file($log_file, FILE_IGNORE_NEW_LINES);
	$log_text = implode("\n", array_slice($lines ?: array(), -100));
	$log_text = preg_replace('/(network_secret\s*=\s*")[^"]*(")/i', '$1********$2', $log_text);
}

include("head.inc");
if (!empty($input_errors)) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>
<?php if ($command_pending): ?>
<script>
window.setTimeout(function () {
	window.location.href = 'vpn_easytier.php?view=status';
}, 2500);
</script>
<?php endif; ?>
<style>
.easytier-panel .panel-body { padding: 15px; }
.easytier-panel .panel-footer { padding: 10px 15px; background: #fafafa; }
.easytier-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.easytier-actions .btn { margin: 0; }
.easytier-summary { margin-bottom: 0; }
.easytier-summary th { width: 220px; white-space: nowrap; }
.easytier-summary th, .easytier-summary td { vertical-align: middle !important; }
.easytier-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
.easytier-toolbar .form-control-static { margin: 0; padding: 0; }
.easytier-peer-panel .panel-body { padding: 0; }
.easytier-peer-table { margin-bottom: 0; }
.easytier-peer-table th { white-space: nowrap; text-align: left; }
.easytier-peer-table td { vertical-align: middle !important; }
.easytier-peer-table th:first-child,
.easytier-peer-table td:first-child { padding-left: 15px !important; }
.easytier-config-label { display: block; margin-bottom: 8px; }
.easytier-config { min-height: 460px; font-family: Menlo, Monaco, Consolas, monospace; font-size: 13px; line-height: 1.45; resize: vertical; }
.easytier-log { min-height: 260px; max-height: 520px; margin: 0; overflow: auto; white-space: pre-wrap; word-break: break-word; background: #f5f5f5; }
.easytier-help { margin: 10px 0 0; color: #666; }
.easytier-state-icon { margin-right: 6px; }
@media (max-width: 767px) {
	.easytier-summary th { width: 42%; white-space: normal; }
	.easytier-toolbar { align-items: flex-start; }
	.easytier-config { min-height: 360px; }
}
</style>

<ul class="nav nav-tabs">
	<li role="presentation" class="<?=$view === 'status' ? 'active' : ''?>"><a href="vpn_easytier.php?view=status"><?=easytier_gettext("Status")?></a></li>
	<li role="presentation" class="<?=$view === 'config' ? 'active' : ''?>"><a href="vpn_easytier.php?view=config"><?=easytier_gettext("Configuration")?></a></li>
	<li role="presentation" class="<?=$view === 'peers' ? 'active' : ''?>"><a href="vpn_easytier.php?view=peers"><?=easytier_gettext("Peers")?></a></li>
	<li role="presentation" class="<?=$view === 'log' ? 'active' : ''?>"><a href="vpn_easytier.php?view=log"><?=easytier_gettext("Log")?></a></li>
</ul>
<br>

<?php if ($view === 'status'): ?>
	<div class="panel panel-default easytier-panel">
		<div class="panel-heading"><h2 class="panel-title"><?=easytier_gettext("EasyTier service status")?></h2></div>
		<div class="panel-body">
			<table class="table table-striped table-hover easytier-summary">
				<tbody>
					<tr><th><?=easytier_gettext("Service status")?></th><td><span class="label label-<?=$running ? 'success' : 'danger'?>"><i class="fa <?=$running ? 'fa-check-circle' : 'fa-stop-circle'?> easytier-state-icon"></i><?=$running ? easytier_gettext("Running") : easytier_gettext("Stopped")?></span></td></tr>
					<tr><th><?=easytier_gettext("Version")?></th><td><?=htmlspecialchars($version ?: easytier_gettext("Unknown"))?></td></tr>
					<tr><th><?=easytier_gettext("Process ID")?></th><td><?=htmlspecialchars($pid ?: '-')?></td></tr>
					<tr><th><?=easytier_gettext("Node name")?></th><td><?=htmlspecialchars($node_name ?: '-')?></td></tr>
					<tr><th><?=easytier_gettext("Virtual address")?></th><td><?=htmlspecialchars($virtual_ip ?: '-')?></td></tr>
					<tr><th><?=easytier_gettext("Network name")?></th><td><?=htmlspecialchars($network_name ?: '-')?></td></tr>
					<tr><th><?=easytier_gettext("Configuration file")?></th><td><code><?=htmlspecialchars($config_file)?></code></td></tr>
				</tbody>
			</table>
		</div>
		<div class="panel-footer">
			<form method="post" class="easytier-actions">
				<button class="btn btn-success" name="action" value="start" type="submit" <?=$running ? 'disabled' : ''?>><i class="fa fa-play icon-embed-btn"></i><?=easytier_gettext("Start")?></button>
				<button class="btn btn-danger" name="action" value="stop" type="submit" <?=$running ? '' : 'disabled'?>><i class="fa fa-stop icon-embed-btn"></i><?=easytier_gettext("Stop")?></button>
				<button class="btn btn-warning" name="action" value="restart" type="submit" <?=$running ? '' : 'disabled'?>><i class="fa fa-refresh icon-embed-btn"></i><?=easytier_gettext("Restart")?></button>
			</form>
		</div>
	</div>
	<?=print_info_box(easytier_gettext("Edit the native TOML file on the Configuration tab. EasyTier must be restarted after configuration changes."), 'info') ;?>

<?php elseif ($view === 'peers'): ?>
	<div class="panel panel-default easytier-panel easytier-peer-panel">
		<div class="panel-heading"><h2 class="panel-title"><?=easytier_gettext("EasyTier peer connection status")?></h2></div>
		<div class="panel-body">
			<?php if (!$running): ?>
				<?=print_info_box(easytier_gettext("EasyTier is stopped. Start the service to view peer connections."), 'warning')?>
			<?php elseif ($peer_error): ?>
				<?php print_input_errors(array($peer_error)); ?>
			<?php elseif (empty($peer_rows)): ?>
				<?=print_info_box(easytier_gettext("No peer information is currently available."), 'info')?>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-striped table-hover table-condensed easytier-peer-table">
						<thead><tr>
							<th><?=easytier_gettext("Virtual IP")?></th><th><?=easytier_gettext("Hostname")?></th><th><?=easytier_gettext("Connection status")?></th>
							<th><?=easytier_gettext("Latency")?></th><th><?=easytier_gettext("Packet loss")?></th><th><?=easytier_gettext("Received")?></th>
							<th><?=easytier_gettext("Sent")?></th><th><?=easytier_gettext("Tunnel")?></th><th><?=easytier_gettext("NAT type")?></th><th><?=easytier_gettext("Version")?></th>
						</tr></thead>
						<tbody>
						<?php foreach ($peer_rows as $peer):
							$connection = strtolower($peer[2]);
							$badge = ($connection === 'local') ? 'primary' : (($connection === 'p2p') ? 'success' : 'warning');
							$connection_label = ($connection === 'local') ? easytier_gettext('Local') : (($connection === 'relay') ? easytier_gettext('Relay') : $peer[2]);
						?>
							<tr>
								<td><?=htmlspecialchars($peer[0])?></td>
								<td><?=htmlspecialchars($peer[1])?></td>
								<td><span class="label label-<?=$badge?>"><?=htmlspecialchars($connection_label)?></span></td>
								<td><?=htmlspecialchars($peer[3])?><?=$peer[3] === '-' ? '' : ' ms'?></td>
								<td><?=htmlspecialchars($peer[4])?></td>
								<td><?=htmlspecialchars($peer[5])?></td>
								<td><?=htmlspecialchars($peer[6])?></td>
								<td><?=htmlspecialchars($peer[7])?></td>
								<td><?=htmlspecialchars($peer[8])?></td>
								<td><?=htmlspecialchars($peer[9])?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

<?php elseif ($view === 'config'): ?>
	<div class="panel panel-default easytier-panel">
		<div class="panel-heading"><h2 class="panel-title"><?=easytier_gettext("EasyTier configuration")?></h2></div>
		<form method="post" action="vpn_easytier.php?view=config">
			<div class="panel-body">
					<div class="form-group" style="margin-bottom:0">
					<label for="config" class="easytier-config-label"><?=easytier_gettext("TOML configuration")?></label>
					<textarea id="config" class="form-control easytier-config" name="config" spellcheck="false" autocomplete="off"><?=htmlspecialchars($config_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></textarea>
				</div>
				<p class="easytier-help"><i class="fa fa-lock"></i> <?=easytier_gettext("The configuration is stored with mode 0600. Network secrets are visible only while editing this administrator page.")?></p>
			</div>
			<div class="panel-footer">
				<div class="easytier-actions">
					<button class="btn btn-primary" name="action" value="save" type="submit"><i class="fa fa-save icon-embed-btn"></i><?=easytier_gettext("Save")?></button>
					<button class="btn btn-warning" name="action" value="save_restart" type="submit"><i class="fa fa-refresh icon-embed-btn"></i><?=easytier_gettext("Save & Restart")?></button>
				</div>
			</div>
		</form>
	</div>

<?php else: ?>
	<div class="panel panel-default easytier-panel">
		<div class="panel-heading"><h2 class="panel-title"><?=easytier_gettext("EasyTier log")?></h2></div>
		<div class="panel-body">
			<div class="easytier-toolbar">
				<span></span>
				<a class="btn btn-default btn-sm" href="vpn_easytier.php?view=log"><i class="fa fa-refresh icon-embed-btn"></i><?=easytier_gettext("Refresh")?></a>
			</div>
			<pre class="easytier-log"><?=htmlspecialchars($log_text ?: easytier_gettext("No log entries."), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></pre>
			<p class="easytier-help"><?=easytier_gettext("Showing the last 100 lines. Network secrets are automatically redacted.")?></p>
		</div>
	</div>
<?php endif; ?>
<?php include("foot.inc"); ?>
