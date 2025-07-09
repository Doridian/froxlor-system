<?php

define('SSL_DIR', '/etc/ssl/froxlor-custom/');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

$fqdn =  getenv('FQDN');

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or die('Database connection error '. mysqli_connect_error());

$ips_res = $db->query('SELECT DISTINCT ip FROM panel_ipsandports;');
$ips = [];
while ($ip_row = $ips_res->fetch_assoc()) {
    $ips[] = $ip_row['ip'];
}
$ips_str = implode(' ', $ips);

$postfix_map_fh = fopen('/etc/postfix/tls_server_sni_maps', 'w');
chmod('/etc/postfix/tls_server_sni_maps', 0640);

$dovecot_tls_fh = fopen('/etc/dovecot/conf.d/zzz-2-tls-sni.conf', 'w');

$pureftpd_tls_fh = fopen('/etc/pure-ftpd/certd.sh', 'w');
fwrite($pureftpd_tls_fh, "#!/bin/bash\n");
fwrite($pureftpd_tls_fh, "set -euo pipefail\n");
chmod('/etc/pure-ftpd/certd.sh', 0755);
fwrite($pureftpd_tls_fh, 'case "$CERTD_SNI_NAME" in' . "\n");

$cert_res = $db->query('SELECT d.domain AS domain, s.ssl_cert_file AS ssl_cert_file FROM panel_domains d, domain_ssl_settings s WHERE d.id = s.domainid;');
while ($cert_row = $cert_res->fetch_assoc()) {
    $domain_raw = $cert_row['domain'];

    $fullchain_file = SSL_DIR . $domain_raw . '_fullchain.pem';
    if (!file_exists($fullchain_file)) {
        echo "Skipping $domain_raw, fullchain file does not exist\n";
        continue;
    }
    $key_file = SSL_DIR . $domain_raw . '.key';
    if (!file_exists($key_file)) {
        echo "Skipping $domain_raw, key file does not exist\n";
        continue;
    }

    $domains = [];

    $cert_data = openssl_x509_parse($cert_row['ssl_cert_file']);
    if (!$cert_data) {
        echo "Skipping $domain_raw, cert data could not be parsed\n";
        continue;
    }

    print_r($cert_data);

    if (!empty($cert_data['subject']['CN'])) {
        $domains[] = $cert_data['subject']['CN'];
    }

    if (isset($cert_data['extensions']['subjectAltName']) && !empty($cert_data['extensions']['subjectAltName'])) {
        $san_array = explode(',', $cert_data['extensions']['subjectAltName']);
        foreach ($san_array as $san) {
            $san = trim($san);
            if (strpos($san, 'DNS:') !== 0) {
                continue;
            }
            $san = substr($san, 4); // Remove 'DNS:' prefix
            if (!empty($san)) {
                $domains[] = $san;
            }
        }
    }

    $domains = array_unique($domains);
    if (empty($domains)) {
        echo "Skipping $domain_raw, no valid domains found in cert\n";
        continue;
    }

    foreach ($domains as $domain) {
        fwrite($postfix_map_fh, $domain . ' ' . $key_file . ' ' . $fullchain_file . "\n");

        fwrite($dovecot_tls_fh, 'local_name ' . $domain . " {\n");
        fwrite($dovecot_tls_fh, "  ssl_cert = <$fullchain_file\n");
        fwrite($dovecot_tls_fh, "  ssl_key = <$key_file\n");
        fwrite($dovecot_tls_fh, "}\n");
    }

    $domains_str = fwrite($pureftpd_tls_fh, '  ' . implode('|', $domains) . ")\n");
    fwrite($pureftpd_tls_fh, "    echo 'cert_file:$fullchain_file'\n");
    fwrite($pureftpd_tls_fh, "    echo 'key_file:$key_file'\n");
    fwrite($pureftpd_tls_fh, "    ;;\n");
}

fwrite($pureftpd_tls_fh, "  *)\n");
fwrite($pureftpd_tls_fh, "    echo 'cert_file:/etc/ssl/froxlor-custom/" . $fqdn . "_fullchain.pem'\n");
fwrite($pureftpd_tls_fh, "    echo 'key_file:/etc/ssl/froxlor-custom/" . $fqdn . ".key'\n");
fwrite($pureftpd_tls_fh, "    ;;\n");
fwrite($pureftpd_tls_fh, "esac\n");

fclose($postfix_map_fh);
fclose($dovecot_tls_fh);
fclose($pureftpd_tls_fh);

passthru('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');
