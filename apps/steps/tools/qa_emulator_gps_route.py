#!/usr/bin/env python3
"""Drive the emulator GPS along a short loop via the emulator console (geo fix).

The gRPC endpoint needs a token this AVD does not hand out, but the telnet
console accepts the same token from ~/.emulator_console_auth_token.
"""
import math
import socket
import sys
import time

TOKEN = open('/home/ihor/.emulator_console_auth_token').read().strip()
LON0, LAT0 = 30.5200, 50.4500
STEPS = int(sys.argv[1]) if len(sys.argv) > 1 else 40
DELAY = float(sys.argv[2]) if len(sys.argv) > 2 else 3.0


def connect():
    sock = socket.create_connection(('127.0.0.1', 5554), timeout=10)
    time.sleep(0.3)
    sock.recv(4096)
    sock.sendall(('auth %s\n' % TOKEN).encode())
    time.sleep(0.3)
    sock.recv(4096)
    return sock


def send(sock, command):
    sock.sendall((command + '\n').encode())
    time.sleep(0.15)
    return sock.recv(4096).decode(errors='replace')


sock = connect()
for i in range(STEPS):
    # ~600 m loop: 0.003 deg latitude per quarter, roughly circular walking pace.
    angle = 2 * math.pi * (i % 24) / 24
    lat = LAT0 + 0.0030 * math.sin(angle)
    lon = LON0 + 0.0045 * math.cos(angle)
    reply = send(sock, 'geo fix %.6f %.6f' % (lon, lat))
    print('%02d %.6f %.6f %s' % (i, lon, lat, reply.strip().replace('\n', ' ')))
    time.sleep(DELAY)
print('done')
