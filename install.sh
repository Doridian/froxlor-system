#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

export INSTALLDIR="$(pwd)"
export SSLDIR="$(php -r 'require_once "shared.php"; echo $ssl_dir;')"

set -x

echo 'Rendering system configuration files...'
rm -rf build && mkdir -p build

find etc -type d -exec mkdir -p "build/"{} \;
find etc -type f -exec /bin/sh -c 'envsubst < "$1" > "build/$1"' -- {} \;

cp -rv build/etc/* /etc/

if ! grep -qF ExtCert /usr/sbin/pure-ftpd-wrapper; then
    echo 'Adding ExtCert to pure-ftpd-wrappers...'
    patch -i pure-ftpd-wrapper.patch /usr/sbin/pure-ftpd-wrapper
fi

rm -rf build

echo 'Running update...'
systemctl daemon-reload
systemctl restart cron
systemctl enable --now pure-certd
systemctl restart pure-certd
systemctl restart pure-ftpd-mysql
./update.php
