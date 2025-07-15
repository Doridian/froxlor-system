#!/bin/sh
postmap -F -q "$CERTD_SNI_NAME" /etc/pure-ftpd/tls_server_sni_maps && exit 0

echo 'action:default'
echo 'end'
