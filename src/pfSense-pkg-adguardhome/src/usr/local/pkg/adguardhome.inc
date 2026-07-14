<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function adguardhome_service_status()
{
    exec('/usr/sbin/service adguardhome status 2>&1', $output, $rc);
    return ($rc === 0) ? 'running' : 'stopped';
}

function adguardhome_start()
{
    mwexec('/usr/sbin/service adguardhome start');
}

function adguardhome_stop()
{
    mwexec('/usr/sbin/service adguardhome stop');
}

function adguardhome_restart()
{
    mwexec('/usr/sbin/service adguardhome restart');
}

function adguardhome_php_install_command()
{
    file_put_contents('/etc/rc.conf.d/adguardhome', "adguardhome_enable=\"YES\"\n");
}

function adguardhome_php_deinstall_command()
{
    @unlink('/etc/rc.conf.d/adguardhome');
}
