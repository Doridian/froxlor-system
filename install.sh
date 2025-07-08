#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

export CERTDIR="/etc/letsencrypt/live/$(hostname -f)"
export INSTALLDIR="$(pwd)"

echo 'Ensuring all LetsEncrypt certificates are present...'
./build_cmd.py | sh -x

echo 'Rendering system configuration files...'
rm -rf build && mkdir -p build

find etc -type d -exec mkdir -p "build/"{} \;
find etc -type f -exec /bin/sh -c 'envsubst < "$1" > "build/$1"' -- {} \;

cp -rv build/etc/* /etc/

rm -rf build

echo 'Adjusting postfix configuration...'
postconf "smtpd_tls_cert_file=$CERTDIR/cert.pem"
postconf "smtpd_tls_key_file=$CERTDIR/privkey.pem"
postconf "smtpd_tls_CAfile=$CERTDIR/chain.pem"

echo 'Restarting services...'
./renew.sh
systemctl restart cron
