<?php

declare(strict_types=1);

function send_common_headers(): void {
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
}

function send_json(array $data): never {
	send_common_headers();
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_SLASHES);
	exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/api/ping') {
	send_json(['ok' => true, 'time' => microtime(true)]);
}

if ($path === '/api/download') {
	$size = min(64 * 1024 * 1024, max(1024, (int)($_GET['size'] ?? 33554432)));
	ignore_user_abort(false);
	set_time_limit(30);
	send_common_headers();
	header('Content-Type: application/octet-stream');
	header('Content-Length: ' . $size);
	header('Content-Encoding: identity');
	$chunk = str_repeat("\0", 1024 * 1024);
	$sent = 0;
	while ($sent < $size && !connection_aborted()) {
		$length = min(strlen($chunk), $size - $sent);
		echo $length === strlen($chunk) ? $chunk : substr($chunk, 0, $length);
		$sent += $length;
		flush();
	}
	exit;
}

if ($path === '/api/upload' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	set_time_limit(30);
	$input = fopen('php://input', 'rb');
	$bytes = 0;
	$started = hrtime(true);
	while (!feof($input)) {
		$data = fread($input, 1024 * 1024);
		if ($data === false) {
			break;
		}
		$bytes += strlen($data);
	}
	$seconds = max(0.000001, (hrtime(true) - $started) / 1e9);
	send_json(['ok' => true, 'bytes' => $bytes, 'seconds' => $seconds]);
}

if ($path !== '/' && $path !== '/index.html') {
	http_response_code(404);
	send_json(['ok' => false, 'error' => 'Not found']);
}

send_common_headers();
header('Content-Type: text/html; charset=utf-8');
readfile('/usr/local/share/lanspeedtest/index.html');
