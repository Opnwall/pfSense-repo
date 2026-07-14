<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function mihomo_service_status()
{
    exec('/usr/sbin/service mihomo status 2>&1', $output, $rc);
    return ($rc === 0) ? 'running' : 'stopped';
}

function mihomo_start()
{
    mwexec('/usr/sbin/service mihomo start');
}

function mihomo_stop()
{
    mwexec('/usr/sbin/service mihomo stop');
}

function mihomo_restart()
{
    mwexec('/usr/sbin/service mihomo restart');
}

function mihomo_tun_exists()
{
    exec('/sbin/ifconfig tun_mihomo >/dev/null 2>&1', $output, $rc);
    return ($rc === 0);
}

function mihomo_generate_rules($type)
{
    if ($type !== 'filter' || !mihomo_tun_exists()) {
        return '';
    }

    $rules = "\n# Mihomo TUN runtime pass rules\n";
    $rules .= 'pass on tun_mihomo inet from any to any flags S/SA keep state label "mihomo_tun_pass"' . "\n";

    return $rules;
}

function mihomo_resync_config()
{
    if (file_exists('/etc/rc.filter_configure')) {
        mwexec('/etc/rc.filter_configure');
    }
}

function mihomo_php_install_command()
{
    file_put_contents('/etc/rc.conf.d/mihomo', "mihomo_enable=\"YES\"\n");
}

function mihomo_php_deinstall_command()
{
    @unlink('/etc/rc.conf.d/mihomo');
    mihomo_resync_config();
}
