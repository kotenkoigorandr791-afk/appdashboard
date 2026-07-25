#!/bin/sh
set -e
need=""
command -v 7z >/dev/null 2>&1 || need="p7zip-full"
command -v unzip >/dev/null 2>&1 || need="${need:+$need }unzip"
[ -z "$need" ] && exit 0
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq --no-install-recommends $need
rm -rf /var/lib/apt/lists/*
