<?php
/* $Id$ */
/*
 *service_arp.php
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2027 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-static-binding
##|*NAME=Services: Static Binding
##|*DESCR=Allow access to the 'Services: Static Binding' page.
##|*MATCH=services_arp.php*
##|-PRIV

require("guiconfig.inc");
global $config;

if (!isset($config['security']) || !is_array($config['security'])) {
	$config['security'] = array();
}
if (!isset($config['security']['arp']) || !is_array($config['security']['arp'])) {
	$config['security']['arp'] = array();
}

function staticarp_language() {
	$candidates = array();
	if (isset($GLOBALS['config']['system']['language'])) {
		$candidates[] = $GLOBALS['config']['system']['language'];
	}
	if (function_exists('config_get_path')) {
		$candidates[] = config_get_path('system/language', null);
	}
	$candidates[] = setlocale(LC_MESSAGES, 0);
	$candidates[] = setlocale(LC_ALL, 0);

	foreach ($candidates as $language) {
		$language = strtolower(str_replace('-', '_', trim((string)$language)));
		if ($language !== '' && $language !== 'c' && $language !== 'posix') {
			return $language;
		}
	}
	return 'en_us';
}

function staticarp_language_family() {
	$language = staticarp_language();
	if (in_array($language, array('zh_cn', 'zh_hans', 'zh_hans_cn'), true) || strpos($language, 'zh_cn') === 0 || strpos($language, 'zh_hans') === 0) {
		return 'zh_Hans';
	}
	if (in_array($language, array('zh_tw', 'zh_hant', 'zh_hant_tw'), true) || strpos($language, 'zh_tw') === 0 || strpos($language, 'zh_hant') === 0) {
		return 'zh_Hant';
	}
	return 'en';
}

function staticarp_t($key) {
	static $messages = array(
		'Services' => array('en' => 'Services', 'zh_Hans' => '服务', 'zh_Hant' => '服務'),
		'Static Binding' => array('en' => 'Static Binding', 'zh_Hans' => '静态绑定', 'zh_Hant' => '靜態綁定'),
		'Interface Settings' => array('en' => 'Interface Settings', 'zh_Hans' => '接口设置', 'zh_Hant' => '介面設定'),
		'Interface' => array('en' => 'Interface', 'zh_Hans' => '接口', 'zh_Hant' => '介面'),
		'Device' => array('en' => 'Device', 'zh_Hans' => '设备', 'zh_Hant' => '裝置'),
		'IP Address' => array('en' => 'IP Address', 'zh_Hans' => 'IP 地址', 'zh_Hant' => 'IP 位址'),
		'MAC Address' => array('en' => 'MAC Address', 'zh_Hans' => 'MAC 地址', 'zh_Hant' => 'MAC 位址'),
		'Status' => array('en' => 'Status', 'zh_Hans' => '状态', 'zh_Hant' => '狀態'),
		'Reply Mode' => array('en' => 'Reply Mode', 'zh_Hans' => '应答模式', 'zh_Hant' => '回應模式'),
		'Script' => array('en' => 'Script', 'zh_Hans' => '脚本', 'zh_Hant' => '指令碼'),
		'Binding Configuration' => array('en' => 'Binding Configuration', 'zh_Hans' => '绑定配置', 'zh_Hant' => '綁定設定'),
		'Static IP Binding' => array('en' => 'Static IP Binding', 'zh_Hans' => '静态 IP 绑定', 'zh_Hant' => '靜態 IP 綁定'),
		'Enable Static ARP Binding' => array('en' => 'Enable static ARP binding', 'zh_Hans' => '启用静态 ARP 绑定', 'zh_Hant' => '啟用靜態 ARP 綁定'),
		'Enable Help' => array('en' => 'When enabled, static ARP entries from the list below are loaded and each interface reply mode is applied.', 'zh_Hans' => '启用后，将按下方列表加载静态 ARP 条目，并应用各接口的 ARP 模式。', 'zh_Hant' => '啟用後，將依下方清單載入靜態 ARP 項目，並套用各介面的 ARP 模式。'),
		'Binding List' => array('en' => 'Binding List', 'zh_Hans' => '绑定列表', 'zh_Hant' => '綁定清單'),
		'Static Binding Entries' => array('en' => 'Binding records', 'zh_Hans' => '绑定记录', 'zh_Hant' => '綁定記錄'),
		'Entry Format Help' => array('en' => 'One entry per line, in <code>IP MAC</code> format.', 'zh_Hans' => '每行一条记录，格式为 <code>IP MAC</code>。', 'zh_Hant' => '每行一筆記錄，格式為 <code>IP MAC</code>。'),
		'Copy ARP Table' => array('en' => 'Copy Current ARP Table', 'zh_Hans' => '复制当前 ARP 表', 'zh_Hant' => '複製目前 ARP 表'),
		'Current ARP Table' => array('en' => 'Current ARP Table', 'zh_Hans' => '当前 ARP 表', 'zh_Hant' => '目前 ARP 表'),
		'Current ARP Help' => array('en' => 'Entries currently learned by the system. Copy them to the left and edit as needed.', 'zh_Hans' => '当前系统学习到的 ARP 条目，可复制到左侧后按需删改。', 'zh_Hant' => '系統目前學習到的 ARP 項目，可複製到左側後依需要修改。'),
		'Save' => array('en' => 'Save', 'zh_Hans' => '保存', 'zh_Hant' => '儲存'),
		'Show Help' => array('en' => 'Show help', 'zh_Hans' => '显示帮助', 'zh_Hant' => '顯示說明'),
		'Help Intro' => array('en' => 'Maintain IP/MAC pairs that are allowed to reach the gateway. Use “Copy Current ARP Table” to copy learned entries to the binding list, then remove entries that should not be fixed before saving.', 'zh_Hans' => '在绑定列表中维护允许访问网关的 IP/MAC 组合。点击“复制当前 ARP 表”可将当前学习到的条目复制到左侧，保存前请删除不需要固定的记录。', 'zh_Hant' => '在綁定清單中維護允許存取閘道的 IP/MAC 組合。點選「複製目前 ARP 表」可將目前學習到的項目複製到左側，儲存前請刪除不需要固定的記錄。'),
		'ARP Mode' => array('en' => 'ARP Mode', 'zh_Hans' => 'ARP 模式', 'zh_Hant' => 'ARP 模式'),
		'Normal Reply Help' => array('en' => '<strong>Normal Reply:</strong> The interface responds to ARP requests using the system default behavior.', 'zh_Hans' => '<strong>正常应答：</strong>接口按系统默认方式响应 ARP 请求。', 'zh_Hant' => '<strong>正常回應：</strong>介面依系統預設方式回應 ARP 請求。'),
		'Static Reply Help' => array('en' => '<strong>Static Reply:</strong> Only clients in the static ARP table receive replies. This is suitable for fixed LAN clients.', 'zh_Hans' => '<strong>静态应答：</strong>仅响应静态 ARP 表中的客户端，适合 LAN 侧固定终端。', 'zh_Hant' => '<strong>靜態回應：</strong>僅回應靜態 ARP 表中的用戶端，適合 LAN 端固定終端。'),
		'No Reply Help' => array('en' => '<strong>No Reply:</strong> The interface does not respond to ARP requests. Use with care unless isolation is required.', 'zh_Hans' => '<strong>取消应答：</strong>接口不响应 ARP 请求，除非明确需要隔离，否则请谨慎使用。', 'zh_Hant' => '<strong>取消回應：</strong>介面不回應 ARP 請求，除非明確需要隔離，否則請謹慎使用。'),
		'Lockout Warning' => array('en' => 'Before enabling binding or switching to Static Reply, make sure the management host is already present in the binding list to avoid losing WebGUI access.', 'zh_Hans' => '建议先确认管理主机已经加入绑定列表，再启用绑定或切换为“静态应答”，以免失去 WebGUI 访问。', 'zh_Hant' => '建議先確認管理主機已加入綁定清單，再啟用綁定或切換為「靜態回應」，以免失去 WebGUI 存取。'),
		'Saved Applied' => array('en' => 'Static binding settings have been saved and applied.', 'zh_Hans' => '静态绑定设置已保存并应用。', 'zh_Hant' => '靜態綁定設定已儲存並套用。'),
		'Saved Disabled' => array('en' => 'Static binding settings have been saved, but binding is currently disabled.', 'zh_Hans' => '静态绑定设置已保存，但当前未启用。', 'zh_Hant' => '靜態綁定設定已儲存，但目前未啟用。'),
		'Invalid IP' => array('en' => 'The binding list contains an invalid IP address: %s', 'zh_Hans' => '列表包含错误的 IP 地址: %s', 'zh_Hant' => '清單包含錯誤的 IP 位址：%s'),
		'Invalid MAC' => array('en' => 'The binding list contains an invalid MAC address: %s', 'zh_Hans' => '列表包含错误的 MAC 地址: %s', 'zh_Hant' => '清單包含錯誤的 MAC 位址：%s'),
		'Empty List' => array('en' => 'The binding list is empty. Static binding cannot be enabled.', 'zh_Hans' => '绑定列表为空，不能启用静态绑定。', 'zh_Hant' => '綁定清單為空，無法啟用靜態綁定。'),
	);
	$family = staticarp_language_family();
	return $messages[$key][$family] ?? $messages[$key]['en'] ?? $key;
}

function staticarp_page_interface_has_upstream_gateway($interface) {
	$gateway = strtolower((string)($interface['gateway'] ?? ''));
	return $gateway !== '' && !in_array($gateway, array('none', 'dynamic'), true);
}

function staticarp_page_interface_is_protected($interfacename, $interface) {
	if (empty($interface['if']) || empty($interface['ipaddr']) || !is_ipaddr($interface['ipaddr'])) {
		return false;
	}
	if (staticarp_page_interface_has_upstream_gateway($interface)) {
		return false;
	}
	return ($interfacename === 'lan' || isset($interface['enable']));
}

function staticarp_page_local_ipv4_addresses() {
	global $config;

	$addresses = array();
	foreach (($config['interfaces'] ?? array()) as $interface) {
		if (!empty($interface['ipaddr']) && is_ipaddr($interface['ipaddr'])) {
			$addresses[$interface['ipaddr']] = true;
		}
	}
	return $addresses;
}

function staticarp_page_config_dir() {
	return '/usr/local/etc/staticarp';
}

function staticarp_page_ensure_config_dir() {
	if (!is_dir(staticarp_page_config_dir())) {
		mkdir(staticarp_page_config_dir(), 0755, true);
	}
}

function staticarp_page_write_arp_file($arplist) {
	$tmpfile = tempnam('/tmp', 'staticarp_');
	$local_addresses = staticarp_page_local_ipv4_addresses();
	$lines = array();
	foreach (preg_split('/\r?\n/', trim(str_replace(',', "\n", $arplist))) as $line) {
		$parts = preg_split('/\s+/', trim($line));
		if (!isset($parts[0], $parts[1]) || !is_ipaddr($parts[0]) || !is_macaddr($parts[1])) {
			continue;
		}
		if (isset($local_addresses[$parts[0]])) {
			continue;
		}
		$lines[] = $parts[0] . ' ' . strtolower($parts[1]);
	}
	file_put_contents($tmpfile, implode("\n", $lines) . "\n");
	return $tmpfile;
}

function staticarp_page_binding_entries() {
	global $config;

	$entries = array();
	$local_addresses = staticarp_page_local_ipv4_addresses();
	$arplist = str_replace(',', "\n", $config['security']['arp']['list'] ?? '');
	foreach (preg_split('/\r?\n/', trim($arplist)) as $line) {
		$parts = preg_split('/\s+/', trim($line));
		if (!isset($parts[0], $parts[1]) || !is_ipaddr($parts[0]) || !is_macaddr($parts[1])) {
			continue;
		}
		if (isset($local_addresses[$parts[0]])) {
			continue;
		}
		$entries[$parts[0]] = strtolower($parts[1]);
	}
	ksort($entries, SORT_NATURAL);
	return $entries;
}

function staticarp_page_write_runtime_config($enabled) {
	global $config;

	staticarp_page_ensure_config_dir();
	file_put_contents(staticarp_page_config_dir() . '/settings.conf', 'enabled=' . ($enabled ? 'YES' : 'NO') . "\n", LOCK_EX);

	$lines = array();
	foreach (staticarp_page_binding_entries() as $ip => $mac) {
		$lines[] = $ip . ' ' . $mac;
	}
	file_put_contents(staticarp_page_config_dir() . '/entries.conf', implode("\n", $lines) . (empty($lines) ? '' : "\n"), LOCK_EX);

	$interfaces = array();
	foreach (($config['interfaces'] ?? array()) as $interfacename => $interface) {
		if (!staticarp_page_interface_is_protected($interfacename, $interface)) {
			continue;
		}
		$mode = $interface['arp'] ?? 'normal';
		$interfaces[] = $interfacename . ' ' . $interface['if'] . ' ' . $mode;
	}
	file_put_contents(staticarp_page_config_dir() . '/interfaces.conf', implode("\n", $interfaces) . (empty($interfaces) ? '' : "\n"), LOCK_EX);
}

function staticarp_page_apply_async($action) {
	$commands = array();
	if (file_exists('/usr/local/sbin/staticarpctl')) {
		$commands[] = '/usr/local/sbin/staticarpctl ' . escapeshellarg($action) . ' >/dev/null 2>&1';
	}
	if (!empty($commands)) {
		mwexec_bg('/bin/sh -c ' . escapeshellarg(implode('; ', $commands)));
	}
}

function staticarp_sync_menu_label() {
	if (!function_exists('config_get_path') || !function_exists('config_set_path')) {
		return;
	}
	$menus = config_get_path('installedpackages/menu', array());
	if (!is_array($menus)) {
		return;
	}
	$changed = false;
	foreach ($menus as $idx => $menu) {
		if (is_array($menu) && (($menu['url'] ?? '') === '/services_arp.php')) {
			if (($menu['name'] ?? '') !== staticarp_t('Static Binding')) {
				$menus[$idx]['name'] = staticarp_t('Static Binding');
				$changed = true;
			}
		}
	}
	if ($changed) {
		config_set_path('installedpackages/menu', $menus);
		if (function_exists('write_config')) {
			write_config('Update Static Binding menu label');
		}
		@unlink('/tmp/config.cache');
	}
}

staticarp_sync_menu_label();

$pgtitle = array(staticarp_t('Services'), staticarp_t('Static Binding'));

$arpcfg = &$config['security']['arp'];
$input_errors = array();
$errors = array();
$curlist = '';
$savemsg = '';

$all_ifinfo = get_all_if_status();
$interfaces = $config['interfaces'];
$arptypes = array(
	'normal' => staticarp_language_family() === 'zh_Hant' ? '正常回應' : (staticarp_language_family() === 'zh_Hans' ? '正常应答' : 'Normal Reply'),
	'staticarp' => staticarp_language_family() === 'zh_Hant' ? '靜態回應' : (staticarp_language_family() === 'zh_Hans' ? '静态应答' : 'Static Reply'),
	'-arp' => staticarp_language_family() === 'zh_Hant' ? '取消回應' : (staticarp_language_family() === 'zh_Hans' ? '取消应答' : 'No Reply'),
);
              
$arplist = trim(str_replace(",","\n",trim($arpcfg['list'] ?? ''))); 
$pconfig['enable'] = isset($config['security']['arp']['enable']);


		 exec("/usr/sbin/arp -an",$rawdata);
		 $data = array();
		 
		 foreach ($rawdata as $line) {
			$elements = preg_split('/\s+/', trim($line));
	
			if (isset($elements[5]) && $elements[3] != "(incomplete)") {
				$arpent = array();
				$arpent['ip'] = trim(str_replace(array('(',')'),'',$elements[1]));
				$arpent['mac'] = trim($elements[3]);
				$arpent['interface'] = trim($elements[5]);
				$data[] = $arpent;
			}
		 }
		 asort($data);
 		 function get_all_if_status() {
	global $config;
	
	$iflist = get_interface_list();
	$if_status = array();
	foreach($config['interfaces'] as $if_name=>$if_info) {
		if(!staticarp_page_interface_is_protected($if_name, $if_info) ) continue;
		$if_info['mac']     = $iflist[$if_info['if']]['mac'];
		$if_info['status']  = $iflist[$if_info['if']]['up']?'up':'down';
		$if_info['mask']	  = gen_subnet_mask_long( $if_info['subnet'] ) ;
		$if_info['net']		  = $if_info['mask'] & ip2long($if_info['ipaddr']) ;
		$if_status[$if_name]	= $if_info;
	}
	return $if_status;
}
function arp_bind() {
	global $config;
	$interfaces = $config['interfaces'];
		if (isset($config['security']['arp']['enable'])) {
			staticarp_page_write_runtime_config(true);
			staticarp_page_apply_async('apply');
			
		} else {
			staticarp_page_write_runtime_config(false);
			staticarp_page_apply_async('reset');
		}
	}
		foreach ($data as $entry) {
			$curlist .= $entry['ip'] . " " . $entry['mac'] . "\n";
		}
			$curlist = ipsort(trim($curlist),"\n");
			//lan arp type
			$pconfig['lanarp'] = $config['interfaces']['lan']['arp'] ?? 'normal';
			// each interface arp type
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			if (isset($config['interfaces']['opt' . $i]['enable'])) {
				$pconfig['opt' . $i . 'arp'] = $config['interfaces']['opt' . $i]['arp'] ?? 'normal';
			}
		}
		
if ($_POST) {
	$pconfig = $_POST;
	if (isset($_POST['copy'])) 
		$arplist = $curlist;

	if (isset($_POST['save'])) {
		unset($lista,$listb,$listc,$errors);
		$lista = trim(str_replace("\n",",",trim($_POST['arplist'])));
		$listb = explode(",", $lista);
		foreach($listb as $listc) {
			if (trim($listc) === '') {
				continue;
			}
			$listd = preg_split('/\s+/', trim($listc));
				if (!is_ipaddr($listd[0]) && $_POST['arplist'] !== "") {
					$input_errors[] = sprintf(staticarp_t('Invalid IP'), $listd[0]);
				}
				if (!isset($listd[1]) || (!is_macaddr($listd[1]) && $_POST['arplist'] !== "")) {
					$mac_error = $listd[1] ?? '';
					$input_errors[] = sprintf(staticarp_t('Invalid MAC'), $mac_error);
				}
		}
		if ($_POST['arplist'] == "" && isset($_POST['enable'])) {
			$input_errors[] = staticarp_t('Empty List');
		}
			
		if (!$input_errors) {
			if (isset($pconfig['enable'])) {
				$arpcfg['enable'] = true;
			} else {
				unset($arpcfg['enable']);
			}
			$arpcfg['list'] = ipsort($lista,",");

					foreach($interfaces as $interfacename => $interface) {
						if (staticarp_page_interface_is_protected($interfacename, $interface)) {
							$arpvalue = $pconfig[$interfacename . 'arp'] ?? 'normal';
							$config['interfaces'][$interfacename]['arp'] = array_key_exists($arpvalue, $arptypes) ? $arpvalue : 'normal';
						}
					}
					write_config("write config.xml");
					arp_bind();
				if (isset($arpcfg['enable'])) {
					$savemsg = staticarp_t('Saved Applied');
				} else {
					$savemsg = staticarp_t('Saved Disabled');
			}
		}
	}
}
$if	= _get_if();
$msg	= false;
$_MOD	= array(
		'Arp' 			=>	array('List','Save','Run','Now','Bat','AllSave'), 
);
$bAry	= array(
		'filter'	=> '规则策略',
		'shaper'	=> '流量整形',
		'arp'		=> 'ARP绑定',
		'staticroutes'	=> '静态路由',
		'captiveportal'	=> '强制门户',
	);
	$mod	= isset($_GET['mod'], $_MOD[$_GET['mod']]) ? $_GET['mod'] : 'Arp';
	$act	= isset($_GET['act']) && in_array($_GET['act'], $_MOD[$mod]) ? $_GET['act'] :'List';
if ($mod === 'Arp' && $act === 'Bat') {
	ArpBat();
}


	function & _get_if(){
		global $config;
		$_if	= get_interface_list();
		$a = array();
		foreach($config['interfaces'] as $k=>$v) {
			if(!staticarp_page_interface_is_protected($k, $v)) continue;
			$v['mac']     = $_if[$v['if']]['mac'];
			$v['status']  = $_if[$v['if']]['up']?'up':'down';
		$v['mask']	  = gen_subnet_mask_long( $v['subnet'] ) ;
		$v['net']		  = $v['mask'] & ip2long($v['ipaddr']) ;
		$a[$k]	= $v;
	}
	return $a;
}
function ArpBat(){
	global $config, $msg, $if;	
	$s	= "@echo off\r\n@color 0A\r\n@echo 		Firewall client arp bind script\r\n@echo #####################################################\r\narp -d\r\n";
	$selected_if = $_GET['if'] ?? 'lan';
	$if	= isset($if[$selected_if]) && is_array($if[$selected_if]) ? $if[$selected_if]  : $if['lan'];
	$s	.= "arp -s {$if['ipaddr']} ". preg_replace("/\:/","-", $if['mac']). "\r\n" ;
	$list	= @explode(',', $config['security']['arp']['list']);
	if(is_array($list)) foreach($list as $l){
		if( preg_match("/^([\d\.]+)\s+([\w\:]+)/", $l, $a)){
			if( ($if['mask'] & ip2long($a[1]) ) === $if['net']){	
				$mac	= preg_replace("/\:/", "-", $a[2]);
				$s	.= "arp -s $a[1] $mac\r\n";
			}
		}
	}
	$s	.= "arp -a\r\n@echo #####################################################\r\npause";
	session_cache_limiter('public');
	header("Content-Type: application/octet-stream");
	header("Content-Length: ".strlen($s) );
	header("Content-Disposition: attachment; filename=\"".htmlentities('arp_'.$if['if'].'.cmd')."\"\n");
	die($s);
}
function ipsort($ip2sort,$char){
	unset($lista,$listb,$listc);
	$tolong = array();
	$toip = '';
	$local_addresses = staticarp_page_local_ipv4_addresses();
	if (trim($ip2sort) === '') {
		return '';
	}
	$lista = explode($char, $ip2sort);
	foreach($lista as $listb) {
		if (trim($listb) === '') {
			continue;
		}
		$listc = preg_split('/\s+/', trim($listb));
		if (!isset($listc[1]) || !is_ipaddr($listc[0]) || !is_macaddr($listc[1])) {
			continue;
		}
		if (isset($local_addresses[$listc[0]])) {
			continue;
		}
		$tolong[]=array("ip" => ip2long(trim($listc[0])),
                  "mac" => trim($listc[1])
    );              
	}
	if (!is_array($tolong)) {
		return '';
	}
	sort($tolong);
	foreach($tolong as $valn){
		$toip .= long2ip($valn['ip']) . " " . $valn['mac'] . $char;
	}
	if ($toip !== ""){
		$toip = substr($toip,0,-1);
	}
	return $toip;
}
	?>
	<?php include("head.inc"); ?>
	<?php if (!empty($input_errors)): ?>
	    <?php print_input_errors($input_errors); ?>
	<?php endif; ?>
	<?php if (!empty($savemsg)): ?>
	    <div class="alert alert-success" role="alert"><?=htmlspecialchars($savemsg)?></div>
	<?php endif; ?>
	<form action="" class="form-horizontal" method="post">
    <div class="panel panel-default">
        <div class="panel-heading">
		            <h2 class="panel-title"><?=htmlspecialchars(staticarp_t('Interface Settings'))?></h2>
        </div>
	        <div class="panel-body" style="padding-left: 15px; padding-right: 15px;">
            <table class="table table-striped table-hover table-condensed sortable-theme-bootstrap">
                <thead>
                    <tr>
	                        <th><?=htmlspecialchars(staticarp_t('Interface'))?></th>
		                        <th><?=htmlspecialchars(staticarp_t('Device'))?></th>
		                        <th><?=htmlspecialchars(staticarp_t('IP Address'))?></th>
		                        <th><?=htmlspecialchars(staticarp_t('MAC Address'))?></th>
		                        <th><?=htmlspecialchars(staticarp_t('Status'))?></th>
		                        <th><?=htmlspecialchars(staticarp_t('Reply Mode'))?></th>
		                        <th>
		                            <div align="center"><?=htmlspecialchars(staticarp_t('Script'))?></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_ifinfo as $name=>$info) {?>
                    <tr>
                        <td>
                            <?=htmlspecialchars($name)?>
                        </td>
                        <td>
                            <?=htmlspecialchars($info['if'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($info['ipaddr'].'/'.$info['subnet'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($info['mac'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($info['status'])?>
                        </td>
                        <td>
                            <select class="form-control" name="<?=htmlspecialchars($name)?>arp">
                                <?php
                    	foreach ($arptypes as $arptype => $arp) {
                    		echo "<option value=\"" . htmlspecialchars($arptype) . "\"";
                    		if ($arptype == ($config['interfaces'][$name]['arp'] ?? 'normal'))
                    			echo " selected";
                    		echo ">" . htmlspecialchars($arp) . "</option>\n";
                    	}
                    ?>
                            </select>
                        </td>
                        <td align="center">
                            <a href="?mod=Arp&act=Bat&if=<?=urlencode($name)?>" class="fa fa-download"></a>
                        </td>
                    </tr>
                    <?php }?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading">
		            <h2 class="panel-title"><?=htmlspecialchars(staticarp_t('Binding Configuration'))?></h2>
        </div>
        <div class="panel-body">
            <div class="form-group">
	                <label class="col-sm-1 control-label">
		                    <span><?=htmlspecialchars(staticarp_t('Static Binding'))?></span>
                </label>
	                <div class="checkbox col-sm-10">
                    <label class="chkboxlbl"><input name="enable" type="checkbox" value="enable" <?php if
		                            ($pconfig['enable']) echo "checked" ; ?>> <?=htmlspecialchars(staticarp_t('Enable Static ARP Binding'))?></label>
		                    <p class="help-block"><?=htmlspecialchars(staticarp_t('Enable Help'))?></p>
                </div>
            </div>
            <div class="form-group">
	                <label class="col-sm-1 control-label">
		                    <span><?=htmlspecialchars(staticarp_t('Binding List'))?></span>
                </label>
                <label for="textarea"></label>
		                <div class="col-sm-5">
		                    <label class="control-label text-left"><?=htmlspecialchars(staticarp_t('Static Binding Entries'))?></label>
	                    <textarea rows="10" class="form-control" name="arplist" placeholder="192.168.10.10 00:11:22:33:44:55"><?=htmlspecialchars($arplist)?></textarea>
		                    <p class="help-block"><?=staticarp_t('Entry Format Help')?></p>
	                    <button class="btn btn-success btn-sm" style="margin-top: 6px;" type="submit" value="copy" name="copy" id="copy"><i
		                            class="fa fa-plus icon-embed-btn"> </i><?=htmlspecialchars(staticarp_t('Copy ARP Table'))?></button>
	                </div>
	                <div class="col-sm-5">
		                    <label class="control-label text-left"><?=htmlspecialchars(staticarp_t('Current ARP Table'))?></label>
	                    <textarea rows="10" class="form-control" name="curlist" readonly><?=htmlspecialchars($curlist)?></textarea>
		                    <p class="help-block"><?=htmlspecialchars(staticarp_t('Current ARP Help'))?></p>
	                </div>
	            </div>
		    <div class="form-group">
			    <div class="col-sm-11 col-sm-offset-1"><button class="btn btn-primary" type="submit" value="save" name="save"><i
			                class="fa fa-save icon-embed-btn"> </i><?=htmlspecialchars(staticarp_t('Save'))?></button></div>
		    </div>
	        </div>
	    </div>
	    <i class="fa fa-info-circle icon-pointer"
        style="color: #337AB7; font-size:20px; margin-left: 10px; margin-bottom: 10px;" id="showinfo0"
		        title="<?=htmlspecialchars(staticarp_t('Show Help'))?>">
    </i>
    <div class="infoblock0">
        <div class="alert alert-info clearfix" role="alert">
            <div class="pull-left">
		                <strong><?=htmlspecialchars(staticarp_t('Static Binding'))?></strong><br />
		                <?=htmlspecialchars(staticarp_t('Help Intro'))?><br /><br />
		                <strong><?=htmlspecialchars(staticarp_t('ARP Mode'))?></strong><br />
	                <ul class="list-unstyled">
		                    <li><?=staticarp_t('Normal Reply Help')?></li>
		                    <li><?=staticarp_t('Static Reply Help')?></li>
		                    <li><?=staticarp_t('No Reply Help')?></li>
	                </ul>
		                <?=htmlspecialchars(staticarp_t('Lockout Warning'))?>
        </div>
    </div>
</form>
<?php
include("foot.inc");
?>
