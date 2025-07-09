#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

export FQDN="$(hostname -f)"

export CERTDIR="/etc/letsencrypt/live/${FQDN}"
export INSTALLDIR="$(pwd)"

eval "$(php froxconf.php)"

echo 'Ensuring all LetsEncrypt certificates are present...'
./build_cmd.py | sh -x

echo 'Rendering system configuration files...'
rm -rf build && mkdir -p build

find etc -type d -exec mkdir -p "build/"{} \;
find etc -type f -exec /bin/sh -c 'envsubst < "$1" > "build/$1"' -- {} \;

cp -rv build/etc/* /etc/

rm -rf build

echo 'Adjusting postfix configuration...'
postconf "smtpd_tls_cert_file=/etc/ssl/froxlor-custom/${FQDN}.crt"
postconf "smtpd_tls_key_file=/etc/ssl/froxlor-custom/${FQDN}.key"
postconf "smtpd_tls_CAfile=/etc/ssl/froxlor-custom/${FQDN}_chain.pem"

postconf 'tls_server_sni_maps=proxy:mysql:/etc/postfix/mysql-tls_server_sni_maps.cf'

echo 'Restarting services...'
./renew.sh
systemctl restart cron
