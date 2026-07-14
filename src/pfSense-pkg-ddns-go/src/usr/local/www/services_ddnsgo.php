<?php
/*
 * services_ddnsgo.php
 * DDNS-Go for pfSense.
 */

$allowautocomplete = true;
$pgtitle = ["服务", "DDNS-Go"];
require_once("guiconfig.inc");
require_once("/usr/local/pkg/ddnsgo.inc");

$ddnsgo_binary = "/usr/local/bin/ddns-go";
$ddnsgo_log = "/var/log/ddnsgo.log";
$message = "";
$message_type = "info";

function ddnsgo_csrf_check() {
	if (function_exists('csrf_check')) {
		return csrf_check();
	}

	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}

	return hash_equals($_SESSION['ddnsgo_csrf_token'] ?? '', $_POST['ddnsgo_csrf_token'] ?? '');
}

function ddnsgo_csrf_token_field() {
	if (function_exists('csrf_token')) {
		csrf_token();
		return;
	}

	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}

	if (empty($_SESSION['ddnsgo_csrf_token'])) {
		try {
			$_SESSION['ddnsgo_csrf_token'] = bin2hex(random_bytes(32));
		} catch (Exception $e) {
			$_SESSION['ddnsgo_csrf_token'] = sha1(uniqid((string)mt_rand(), true));
		}
	}

	echo '<input type="hidden" name="ddnsgo_csrf_token" value="' .
		htmlspecialchars($_SESSION['ddnsgo_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
		'">';
}

function ddnsgo_service_action_result($action, $binary) {
	if (!in_array($action, ['start', 'stop', 'restart'], true)) {
		return ['message' => '无效的服务操作。', 'type' => 'danger'];
	}

	if (($action === 'start' || $action === 'restart') && !is_executable($binary)) {
		return ['message' => 'DDNS-Go 二进制文件不存在或不可执行：' . $binary, 'type' => 'danger'];
	}

	if ($action === 'start') {
		ddnsgo_start();
	} elseif ($action === 'stop') {
		ddnsgo_stop();
	} else {
		ddnsgo_restart();
	}

	return ['message' => '服务命令已执行。', 'type' => 'success'];
}

function ddnsgo_save_config_file($file, $content) {
	if (trim($content) === '') {
		return ['message' => '配置文件内容不能为空。', 'type' => 'danger'];
	}

	$target_dir = dirname($file);
	if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
		return ['message' => '无法创建配置目录。', 'type' => 'danger'];
	}

	if (!is_writable($target_dir)) {
		return ['message' => '配置目录不可写。', 'type' => 'danger'];
	}

	$tmp_file = tempnam($target_dir, 'ddnsgo_save_');
	if ($tmp_file === false) {
		return ['message' => '无法创建临时配置文件。', 'type' => 'danger'];
	}

	if (file_put_contents($tmp_file, $content, LOCK_EX) === false) {
		@unlink($tmp_file);
		return ['message' => '无法写入临时配置文件。', 'type' => 'danger'];
	}

	$original_perms = file_exists($file) ? (@fileperms($file) & 0777) : 0600;
	if (!@rename($tmp_file, $file)) {
		@unlink($tmp_file);
		return ['message' => '无法替换配置文件。', 'type' => 'danger'];
	}

	@chmod($file, $original_perms);
	ddnsgo_restart();
	return ['message' => '配置文件已保存并重启服务。', 'type' => 'success'];
}

function ddnsgo_tail_log($file, $lines = 200) {
	if (!is_readable($file)) {
		return '日志文件不存在或无法读取。';
	}

	$output = [];
	exec('/usr/bin/tail -n ' . escapeshellarg((string)$lines) . ' ' . escapeshellarg($file) . ' 2>&1', $output);
	return implode("\n", $output);
}

if (($_GET['status'] ?? '') === '1') {
	header('Content-Type: application/json');
	echo json_encode(['status' => ddnsgo_is_running() ? 'running' : 'stopped']);
	exit;
}

if (($_GET['log'] ?? '') === '1') {
	header('Content-Type: text/plain; charset=UTF-8');
	echo ddnsgo_tail_log($ddnsgo_log);
	exit;
}

$ddnsgo_config = ddnsgo_get_config();
$config_path = $ddnsgo_config['config_path'] ?? '/usr/local/etc/ddns-go/config.yaml';

if ($_POST) {
	if (!ddnsgo_csrf_check()) {
		$result = ['message' => '请求校验失败，请刷新页面后重试。', 'type' => 'danger'];
	} else {
		$action = $_POST['action'] ?? '';
		if ($action === 'save_config') {
			$result = ddnsgo_save_config_file($config_path, $_POST['config_content'] ?? '');
		} else {
			$result = ddnsgo_service_action_result($action, $ddnsgo_binary);
		}
	}

	$message = $result['message'];
	$message_type = $result['type'];
}

$running = ddnsgo_is_running();
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);
$listen = ddnsgo_normalize_listen($ddnsgo_config['listen'] ?? ':9876');
$port = '9876';
if (preg_match('/:(\d+)$/', $listen, $matches)) {
	$port = $matches[1];
}
$access_url = 'http://' . ($host ?: '127.0.0.1') . ':' . $port . '/';
$config_content = is_readable($config_path) ? file_get_contents($config_path) : '';

include("head.inc");
?>

<?php if (!empty($message)): ?>
	<div class="alert alert-<?= htmlspecialchars($message_type); ?>">
		<?= nl2br(htmlspecialchars($message)); ?>
	</div>
<?php endif; ?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">服务状态</h2>
	</div>
	<div class="panel-body">
		<div id="ddnsgo-status" class="alert <?= $running ? 'alert-success' : 'alert-danger' ?>">
			<i class="fa <?= $running ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
			<?= htmlspecialchars($running ? 'DDNS-Go 正在运行' : 'DDNS-Go 已停止'); ?>
		</div>
		<table class="table table-striped table-condensed">
			<tbody>
				<tr>
					<th style="width: 180px;">访问地址</th>
					<td><a href="<?= htmlspecialchars($access_url); ?>" target="_blank"><?= htmlspecialchars($access_url); ?></a></td>
				</tr>
				<tr>
					<th>配置文件</th>
					<td><code><?= htmlspecialchars($config_path); ?></code></td>
				</tr>
				<tr>
					<th>服务控制</th>
					<td>
						<form method="post" class="form-inline">
							<?php ddnsgo_csrf_token_field(); ?>
							<button type="submit" name="action" value="start" class="btn btn-success btn-sm" <?= $running ? 'disabled' : ''; ?>>
								<i class="fa fa-play"></i> 启动
							</button>
							<button type="submit" name="action" value="stop" class="btn btn-danger btn-sm" <?= $running ? '' : 'disabled'; ?>>
								<i class="fa fa-stop"></i> 停止
							</button>
							<button type="submit" name="action" value="restart" class="btn btn-warning btn-sm">
								<i class="fa fa-refresh"></i> 重启
							</button>
						</form>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">配置文件</h2>
	</div>
	<div class="panel-body">
		<form method="post">
			<?php ddnsgo_csrf_token_field(); ?>
			<textarea name="config_content" rows="14" class="form-control" style="font-family: monospace;"><?= htmlspecialchars($config_content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
			<br>
			<button type="submit" name="action" value="save_config" class="btn btn-primary">
				<i class="fa fa-save"></i> 保存配置
			</button>
		</form>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">日志</h2>
	</div>
	<div class="panel-body">
		<textarea id="ddnsgo-log" rows="14" class="form-control" readonly></textarea>
	</div>
</div>

<script>
const ddnsGoI18n = <?= json_encode([
	'running' => 'DDNS-Go 正在运行',
	'stopped' => 'DDNS-Go 已停止',
	'status_failed' => '状态检查失败',
	'log_failed' => '无法加载日志。',
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function checkDdnsGoStatus() {
	fetch('services_ddnsgo.php?status=1')
		.then(response => {
			if (!response.ok) throw new Error('bad status response');
			return response.json();
		})
		.then(data => {
			const statusElement = document.getElementById('ddnsgo-status');
			if (data.status === 'running') {
				statusElement.innerHTML = '<i class="fa fa-check-circle text-success"></i> ' + ddnsGoI18n.running;
				statusElement.className = 'alert alert-success';
			} else {
				statusElement.innerHTML = '<i class="fa fa-times-circle text-danger"></i> ' + ddnsGoI18n.stopped;
				statusElement.className = 'alert alert-danger';
			}
		})
		.catch(() => {
			const statusElement = document.getElementById('ddnsgo-status');
			statusElement.innerHTML = '<i class="fa fa-exclamation-triangle text-warning"></i> ' + ddnsGoI18n.status_failed;
			statusElement.className = 'alert alert-warning';
		});
}

function refreshDdnsGoLog() {
	fetch('services_ddnsgo.php?log=1')
		.then(response => {
			if (!response.ok) throw new Error('bad log response');
			return response.text();
		})
		.then(text => {
			document.getElementById('ddnsgo-log').value = text;
		})
		.catch(() => {
			document.getElementById('ddnsgo-log').value = ddnsGoI18n.log_failed;
		});
}

document.addEventListener('DOMContentLoaded', () => {
	checkDdnsGoStatus();
	refreshDdnsGoLog();
	setInterval(checkDdnsGoStatus, 3000);
	setInterval(refreshDdnsGoLog, 5000);
});
</script>

<?php include("foot.inc"); ?>
