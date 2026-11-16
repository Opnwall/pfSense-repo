"""Check optional EasyTier startup and repeat-start behavior of the rc script."""
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
RC = ROOT / 'src/pfSense-pkg-sing-box/src/usr/local/etc/rc.d/sing-box'


class StartupTest(unittest.TestCase):
    def run_start(self, enabled=True, valid=True, easytier_starts=True, already_running=False):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            trace = root / 'trace'
            binary = root / 'sing-box'
            binary.write_text('#!/bin/sh\necho check >> "$TRACE"\nexit ' + ('0' if valid else '1') + '\n')
            binary.chmod(0o755)
            config = root / 'config'
            config.touch()
            source = RC.read_text().replace('. /etc/rc.subr', ':')
            source = source.replace('load_rc_config "$name"', ':').replace('run_rc_command "$1"', ':')
            source = source.replace('/usr/sbin/service', 'mock_service')
            source += f'''
TRACE='{trace}'
export TRACE
log_file='{root / 'log'}'
sing_box_bin='{binary}'
sing_box_config='{config}'
service() {{ return 1; }}
mock_service() {{
    case "$*" in
        -e) {'echo /usr/local/etc/rc.d/easytier' if enabled else ':'} ;;
        'easytier status') return 1 ;;
        'easytier start') echo easytier-start >> "$TRACE"; return {0 if easytier_starts else 1} ;;
        *) return 2 ;;
    esac
}}
'''
            if already_running:
                source += '''
sing_box_status() { return 0; }
sing_box_prestart() { echo unexpected-prestart >> "$TRACE"; return 1; }
sing_box_start
'''
            else:
                source += 'sing_box_prestart\n'
            result = subprocess.run(['sh'], input=source, text=True, capture_output=True, timeout=5)
            return result.returncode, trace.read_text().splitlines() if trace.exists() else []

    def test_enabled_easytier_starts_after_config_validation(self):
        self.assertEqual(self.run_start(), (0, ['check', 'easytier-start']))

    def test_standalone_sing_box_does_not_start_disabled_easytier(self):
        self.assertEqual(self.run_start(enabled=False), (0, ['check']))

    def test_invalid_config_does_not_start_other_services(self):
        self.assertEqual(self.run_start(valid=False), (1, ['check']))

    def test_easytier_start_failure_propagates(self):
        self.assertEqual(self.run_start(easytier_starts=False), (1, ['check', 'easytier-start']))

    def test_repeated_start_does_not_repeat_prestart_or_spawn(self):
        self.assertEqual(self.run_start(already_running=True), (0, []))


if __name__ == '__main__':
    unittest.main()
