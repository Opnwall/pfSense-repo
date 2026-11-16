"""Check that independent boot registrations coexist and remain idempotent."""
from pathlib import Path
import json
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]


@unittest.skipUnless(shutil.which('php'), 'PHP is required for package-hook tests')
class AutostartTest(unittest.TestCase):
    def boot_code(self, package):
        hook = ROOT / f'src/pfSense-pkg-{package}/packaging/freebsd/+POST_INSTALL'
        text = hook.read_text()
        start = text.index(f"$cmd = 'service {package} start';")
        end = text.index('\nPHP', start)
        code = text[start:end]
        return re.sub(r"require_once\([^\n]+\);", '', code)

    def test_installing_either_order_keeps_both_boot_commands(self):
        for order in [('sing-box', 'easytier'), ('easytier', 'sing-box')]:
            with self.subTest(order=order):
                code = '''<?php
$settings = ['system/shellcmd' => ['custom-startup'],
    'installedpackages/shellcmdsettings/config' => [
        ['cmd' => 'custom-startup', 'cmdtype' => 'shellcmd']]];
function config_get_path($path, $default) { global $settings; return $settings[$path] ?? $default; }
function config_set_path($path, $value) { global $settings; $settings[$path] = $value; }
function write_config($message) {}
'''
                for package in order * 2:
                    code += self.boot_code(package) + '\n'
                code += 'echo json_encode($settings);'
                result = subprocess.run(['php'], input=code, text=True, capture_output=True, check=True)
                settings = json.loads(result.stdout)
                expected = ['custom-startup', 'service sing-box start', 'service easytier start']
                self.assertCountEqual(settings['system/shellcmd'], expected)
                entries = settings['installedpackages/shellcmdsettings/config']
                self.assertCountEqual([entry['cmd'] for entry in entries], expected)

    def test_easytiers_poststart_only_reloads_its_filter(self):
        rc = ROOT / 'src/pfSense-pkg-easytier/src/usr/local/etc/rc.d/easytier'
        code = rc.read_text().replace('. /etc/rc.subr', ':')
        code = code.replace('load_rc_config "${name}"', ':').replace('run_rc_command "$1"', ':')
        code = code.replace('/sbin/ifconfig', 'mock_ifconfig')
        code += '''
mock_ifconfig() { return 0; }
easytier_reload_filter() { echo filter-reloaded; }
easytier_poststart
'''
        result = subprocess.run(['sh'], input=code, text=True, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout.strip(), 'filter-reloaded')


if __name__ == '__main__':
    unittest.main()
