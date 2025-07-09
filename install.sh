#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

export FQDN="$(hostname -f)"
export INSTALLDIR="$(pwd)"

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

echo 'Adjusting postfix configuration...'
postconf "smtpd_tls_cert_file=/etc/ssl/froxlor-custom/${FQDN}.crt"
postconf "smtpd_tls_key_file=/etc/ssl/froxlor-custom/${FQDN}.key"
postconf "smtpd_tls_CAfile=/etc/ssl/froxlor-custom/${FQDN}_chain.pem"
postconf "smtpd_tls_chain_files=/etc/ssl/froxlor-custom/${FQDN}.key,/etc/ssl/froxlor-custom/${FQDN}_fullchain.pem"

postconf 'tls_server_sni_maps=hash:/etc/postfix/tls_server_sni_maps'

echo 'Running update...'
systemctl daemon-reload
systemctl restart cron
systemctl enable --now pure-certd
./update.sh
