# /etc/postfix/sasl_passwd should contain the following line:
# [email-smtp.eu-west-1.amazonaws.com]:587 ACCESS_KEY:SECRET_KEY

chmod 600 /etc/postfix/sasl_passwd
chown root:root /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd
chmod 640 /etc/postfix/sasl_passwd.db
chown root:postfix /etc/postfix/sasl_passwd.db

postconf 'smtp_sasl_password_maps=hash:/etc/postfix/sasl_passwd'
postconf 'smtp_sasl_security_options=noanonymous'
postconf "relayhost=[email-smtp.eu-west-1.amazonaws.com]:587"
postconf "smtp_sasl_auth_enable=yes"

systemctl restart postfix
