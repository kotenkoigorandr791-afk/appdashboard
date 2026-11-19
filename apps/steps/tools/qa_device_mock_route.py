#!/usr/bin/env python3
"""Walk a mock GPS route on the emulator through Android's test location provider.

The emulator's own `geo fix` console command is not delivered by the GNSS HAL in
this headless AVD, so the `gps` provider is replaced with a test provider and
fed one location per step. Requires adb running as root (adb root).

    adb root
    adb shell cmd location providers add-test-provider gps --requiresSatellite
    adb shell cmd location providers set-test-provider-enabled gps true
    python3 apps/steps/tools/qa_device_mock_route.py [points] [delay_s]
"""
import math
import subprocess
import sys
import time

POINTS = int(sys.argv[1]) if len(sys.argv) > 1 else 24
DELAY = float(sys.argv[2]) if len(sys.argv) > 2 else 3.0
LON0, LAT0 = 30.5200, 50.4500
RADIUS_LAT = 0.0016   # ~180 m
RADIUS_LON = 0.0024   # ~165 m
ACCURACY_M = 8


def adb(*args: str) -> str:
    result = subprocess.run(['adb', *args], capture_output=True, text=True)
    return (result.stdout + result.stderr).strip()


previous = None
for i in range(POINTS):
    angle = 2 * math.pi * (i % POINTS) / POINTS
    lat = LAT0 + RADIUS_LAT * math.sin(angle)
    lon = LON0 + RADIUS_LON * math.cos(angle)
    reply = adb(
        'shell', 'cmd', 'location', 'providers', 'set-test-provider-location', 'gps',
        '--location', '%.6f,%.6f' % (lat, lon), '--accuracy', str(ACCURACY_M),
    )
    if previous is not None:
        dlat = (lat - previous[0]) * 111_320
        dlon = (lon - previous[1]) * 111_320 * math.cos(math.radians(lat))
        step = math.hypot(dlat, dlon)
    else:
        step = 0.0
    previous = (lat, lon)
    print('%02d %.6f %.6f step=%.0fm %s' % (i, lat, lon, step, reply.replace('\n', ' ')))
    time.sleep(DELAY)
print('done')
