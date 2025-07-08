#!/bin/bash
set -euo pipefail

cd /root/froxlor-letsencrypt-system

if [ ! -d /etc/letsencrypt/live/arcticfox.doridian.net ]; then
	echo 'LetsEncrypt certs missing, please run'
	./build_cmd.py
	exit 1
fi

cp -rv etc/* /etc/

postmap /etc/postfix/sasl_passwd
chmod 640 /etc/postfix/sasl_passwd.db
chown root:postfix /etc/postfix/sasl_passwd.db

postconf 'smtp_sasl_password_maps=hash:/etc/postfix/sasl_passwd'
postconf 'smtp_sasl_security_options=noanonymous'
postconf 'relayhost=[email-smtp.eu-west-1.amazonaws.com]:587'
postconf 'smtp_sasl_auth_enable=yes'
postconf 'smtpd_tls_cert_file=/etc/letsencrypt/live/arcticfox.doridian.net/cert.pem'
postconf 'smtpd_tls_key_file =/etc/letsencrypt/live/arcticfox.doridian.net/privkey.pem'
postconf 'smtpd_tls_CAfile=/etc/letsencrypt/live/arcticfox.doridian.net/chain.pem'

./renew.sh
