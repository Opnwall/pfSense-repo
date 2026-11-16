"""Check standalone config validation and repeat-start behavior of sing-box."""
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
RC = ROOT / 'src/pfSense-pkg-sing-box/src/usr/local/etc/rc.d/sing-box'


class StartupTest(unittest.TestCase):
    def run_start(self, valid=True, already_running=False):
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
            source = source.replace('/usr/sbin/service', 'service')
            source += f'''
TRACE='{trace}'
export TRACE
log_file='{root / 'log'}'
sing_box_bin='{binary}'
sing_box_config='{config}'
service() {{
    if [ "$*" = 'mihomo status' ]; then return 1; fi
    echo "unexpected-service:$*" >> "$TRACE"
    return 1
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

    def test_valid_config_needs_no_other_service(self):
        self.assertEqual(self.run_start(), (0, ['check']))

    def test_invalid_config_does_not_start_other_services(self):
        self.assertEqual(self.run_start(valid=False), (1, ['check']))

    def test_repeated_start_does_not_repeat_prestart_or_spawn(self):
        self.assertEqual(self.run_start(already_running=True), (0, []))


if __name__ == '__main__':
    unittest.main()
