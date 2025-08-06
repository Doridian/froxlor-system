#!/bin/bash
set -euo pipefail
set -x

cd "$(dirname "$0")"

export INSTALLDIR="$(pwd)"

apt-get -y update
apt-get -y install unattended-upgrades restic screen htop phpmyadmin sudo mariadb-backup ncdu git nfs-common rsync

echo 'Rendering system configuration files...'
rm -rf build && mkdir -p build

find etc -type d -print0 | xargs -0 -n1 mkdir -p "build/$1"
find etc -type f -print0 | xargs -0 -n1 sed "s~__INSTALLDIR__~${INSTALLDIR}~g" "build/$1"

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
