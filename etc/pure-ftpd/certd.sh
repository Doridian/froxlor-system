#!/bin/sh
set -e

defconf() {
    echo 'action:default'
    echo 'end'
}

postmap -F -q "$CERTD_SNI_NAME" /etc/pure-ftpd/tls_server_sni_maps || defconf
