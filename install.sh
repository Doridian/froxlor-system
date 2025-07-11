#!/bin/bash
set -euo pipefail
set -x

cd "$(dirname "$0")"

export INSTALLDIR="$(pwd)"

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

postconf -e tls_server_sni_maps=proxy:hash:/etc/postfix/tls_server_sni_maps inet_protocols=all
postconf -X smtpd_tls_CAfile smtpd_tls_cert_file smtpd_tls_keyFile

echo 'Running update...'
systemctl daemon-reload
systemctl restart cron
systemctl enable --now pure-certd
systemctl restart pure-certd
systemctl restart pure-ftpd-mysql
./update.php
systemctl restart dovecot
systemctl restart postfix
