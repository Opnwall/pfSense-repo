<?php
/*
 * mihomo_logs.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-mihomo
##|*NAME=Services: Mihomo
##|*DESCR=Mihomo service configuration.
##|*MATCH=mihomo_logs.php*
##|-PRIV
require_once("guiconfig.inc");

$log_file = "/var/log/mihomo.log";
$display_lines = 200;

function mihomo_log_t($text)
{
    $language = function_exists('config_get_path') ? (string)config_get_path('system/language', 'en') : (string)($GLOBALS['config']['system']['language'] ?? 'en');
    $language = strtolower(str_replace('-', '_', $language));
    $zh = [
        'Error' => '错误',
        'Log file was not found.' => '日志文件未找到！',
    ];
    $ru = [
        'Error' => 'Ошибка',
        'Log file was not found.' => 'Файл журнала не найден.',
    ];

    if (in_array($language, ['zh_cn', 'zh_hans_cn'], true)) {
        return $zh[$text] ?? $text;
    }
    if ($language === 'ru_ru') {
        return $ru[$text] ?? $text;
    }

    return $text;
}

header('Content-Type: text/plain; charset=UTF-8');

if (!file_exists($log_file)) {
    echo "[" . mihomo_log_t('Error') . "] " . mihomo_log_t('Log file was not found.');
    exit;
}

$log = new SplFileObject($log_file, 'r');
$log->seek(PHP_INT_MAX);
$total_lines = $log->key();

$log_content = [];

$start_line = max(0, $total_lines - $display_lines);
$log->seek($start_line);

while (!$log->eof()) {
    $log_content[] = trim($log->fgets());
}

echo implode("\n", array_filter($log_content, static function ($line) {
    return $line !== '';
}));
?>
