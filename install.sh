#!/bin/bash
set -euo pipefail

FQDN=$(hostname -f)

cd /root/froxlor-letsencrypt-system

if [ ! -d "/etc/letsencrypt/live/$FQDN" ]; then
	echo 'LetsEncrypt certs missing, please run'
	./build_cmd.py
	exit 1
fi

cp -rv etc/* /etc/

postconf "smtpd_tls_cert_file=/etc/letsencrypt/live/$FQDN/cert.pem"
postconf "smtpd_tls_key_file=/etc/letsencrypt/live/$FQDN/privkey.pem"
postconf "smtpd_tls_CAfile=/etc/letsencrypt/live/$FQDN/chain.pem"

./renew.sh
