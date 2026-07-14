<?php
/*
 * sing-box_log.php
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
##|*IDENT=page-services-sing-box
##|*NAME=Services: Sing-Box
##|*DESCR=Sing-Box service configuration.
##|*MATCH=sing-box_log.php*
##|-PRIV
require_once("guiconfig.inc");

$log_file = "/var/log/sing-box.log";

function singbox_log_t($text)
{
    $language = function_exists('config_get_path') ? (string)config_get_path('system/language', 'en') : (string)($GLOBALS['config']['system']['language'] ?? 'en');
    $language = strtolower(str_replace('-', '_', $language));
    $zh = [
        'Log file does not exist or cannot be read.' => '日志文件不存在或无法读取。',
    ];
    $ru = [
        'Log file does not exist or cannot be read.' => 'Файл журнала не существует или не может быть прочитан.',
    ];

    if (in_array($language, ['zh_cn', 'zh_hans_cn'], true)) {
        return $zh[$text] ?? $text;
    }
    if ($language === 'ru_ru') {
        return $ru[$text] ?? $text;
    }

    return $text;
}

header('Content-Type: text/plain');

if (file_exists($log_file) && is_readable($log_file)) {
    $lines = 200;
    $log_content = shell_exec("tail -n $lines " . escapeshellarg($log_file));
    echo $log_content;
} else {
    echo singbox_log_t('Log file does not exist or cannot be read.');
}
