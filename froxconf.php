<?php

define('SSL_DIR', '/etc/ssl/froxlor-custom');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or die('Database connection error '. mysqli_connect_error());

$cert_res = $db->query("SELECT domain, wwwserveralias FROM panel_domains;");

$postfix_map_fh = fopen('/etc/postfix/tls_server_sni_maps', 'w');
chmod('/etc/postfix/tls_server_sni_maps', 0640);
$dovecot_tls_fh = fopen('/etc/dovecot/conf.d/tls-sni.conf', 'w');
chmod('/etc/dovecot/conf.d/tls-sni.conf', 0640);

function add_domain($domain, $key_file, $fullchain_file) {
    global $postfix_map_fh, $dovecot_tls_fh;

    fwrite($postfix_map_fh, $domain . ' ' . $key_file . ' ' . $fullchain_file . "\n");

    fwrite($dovecot_tls_fh, 'local_name ' . $domain . " {\n");
    fwrite($dovecot_tls_fh, "  ssl_server_cert_file = $fullchain_file\n");
    fwrite($dovecot_tls_fh, "  ssl_server_key_file = $key_file\n");
    fwrite($dovecot_tls_fh, "}\n");
}

while ($cert_row = $cert_res->fetch_assoc()) {
    $domain = $cert_row['domain'];

    $fullchain_file = SSL_DIR . '/' . $domain . '_fullchain.pem';
    if (!file_exists($fullchain_file)) {
        continue;
    }

    $key_file = SSL_DIR . '/' . $domain . '.key';
    if (!file_exists($key_file)) {
        continue;
    }

    add_domain($domain, $key_file, $fullchain_file);
    if ($cert_row['wwwserveralias']) {
        add_domain('www.' . $domain, $key_file, $fullchain_file);
    }
}

fclose($postfix_map_fh);
fclose($dovecot_tls_fh);

passthru('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');
