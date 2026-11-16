"""Exercise the route recovery script without changing the host routing table."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / 'src/pfSense-pkg-easytier/src/usr/local/sbin/easytier-sing-box-routes'


class BootRoutesTest(unittest.TestCase):
    def run_helper(self, fibs=2023, add_fails=False, change_fails=False,
                   interface_ready=True, routes=None):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            log = root / 'commands'
            fixture = root / 'routes'
            fixture.write_text(routes if routes is not None else '''Routing tables
Internet:
Destination Gateway Flags Netif Expire
default 192.0.2.1 UGS vmx1
10.44.0.0/24 link#7 US easytier0
10.44.0.8 link#7 UH easytier0
192.168.18.0/24 link#7 US easytier0
192.168.101.0/24 link#1 U vmx0
''')
            stubs = {
                '/sbin/ifconfig': 'echo "inet 10.44.0.8"' if interface_ready else 'exit 1',
                '/sbin/sysctl': f'echo {fibs}',
                '/usr/bin/netstat': f'cat "{fixture}"',
                '/usr/sbin/setfib': '''echo "$*" >> "$COMMAND_LOG"
case "$3" in
add) exit "$ADD_FAILS" ;;
change) exit "$CHANGE_FAILS" ;;
esac
exit 2''',
                '/usr/bin/logger': 'exit 0',
                'sleep 1': ':',
            }
            source = HELPER.read_text()
            for index, (command, body) in enumerate(stubs.items()):
                if command == 'sleep 1':
                    source = source.replace(command, body)
                    continue
                stub = root / f'command-{index}'
                stub.write_text('#!/bin/sh\n' + body + '\n')
                stub.chmod(0o755)
                source = source.replace(command, str(stub))
            script = root / 'helper'
            script.write_text(source)
            env = dict(os.environ, COMMAND_LOG=str(log), ADD_FAILS=str(int(add_fails)),
                       CHANGE_FAILS=str(int(change_fails)))
            result = subprocess.run(['sh', str(script)], env=env, capture_output=True, timeout=5)
            return result.returncode, log.read_text().splitlines() if log.exists() else []

    def test_cold_boot_before_sing_box_creates_fib(self):
        self.assertEqual(self.run_helper(fibs=1), (0, []))

    def test_mirrors_actual_easytier_networks_only(self):
        self.assertEqual(self.run_helper(), (0, [
            '2022 /sbin/route add -net 10.44.0.0/24 -interface easytier0',
            '2022 /sbin/route add -net 192.168.18.0/24 -interface easytier0',
        ]))

    def test_repeated_start_changes_existing_routes(self):
        rc, commands = self.run_helper(add_fails=True)
        self.assertEqual(rc, 0)
        self.assertEqual(len(commands), 4)
        self.assertIn('change -net 10.44.0.0/24', commands[1])
        self.assertIn('change -net 192.168.18.0/24', commands[3])

    def test_route_failure_is_reported(self):
        rc, _ = self.run_helper(add_fails=True, change_fails=True)
        self.assertNotEqual(rc, 0)

    def test_missing_interface_times_out_without_routes(self):
        self.assertEqual(self.run_helper(interface_ready=False), (1, []))

    def test_missing_networks_are_reported(self):
        self.assertEqual(self.run_helper(routes='default 192.0.2.1 UGS vmx1\n'), (1, []))


if __name__ == '__main__':
    unittest.main()
