<?php
/*
 * sing-box_sub_log.php
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
##|*IDENT=page-services-sing-box-sub
##|*NAME=Services: Sing-Box Sub
##|*DESCR=Sing-Box Sub service configuration.
##|*MATCH=sing-box_sub_log.php*
##|-PRIV
require_once("guiconfig.inc");

$log_file = '/var/log/sing-box_sub.log';
$max_lines = 200;

function singbox_sub_log_t($text)
{
    $language = function_exists('config_get_path') ? (string)config_get_path('system/language', 'en') : (string)($GLOBALS['config']['system']['language'] ?? 'en');
    $language = strtolower(str_replace('-', '_', $language));
    $zh = [
        'Notice' => '提示',
        'Error' => '错误',
        'Log file does not exist: %s' => '日志文件不存在：%s',
        'Unable to read the log file.' => '无法读取日志文件。',
    ];
    $ru = [
        'Notice' => 'Уведомление',
        'Error' => 'Ошибка',
        'Log file does not exist: %s' => 'Файл журнала не существует: %s',
        'Unable to read the log file.' => 'Не удалось прочитать файл журнала.',
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
    echo "[" . singbox_sub_log_t('Notice') . "] " . sprintf(singbox_sub_log_t('Log file does not exist: %s'), $log_file) . "\n";
    exit;
}

$lines = @file($log_file, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    echo "[" . singbox_sub_log_t('Error') . "] " . singbox_sub_log_t('Unable to read the log file.') . "\n";
    exit;
}

$tail = array_slice($lines, -$max_lines);
echo implode("\n", $tail);
if (!empty($tail)) {
    echo "\n";
}
