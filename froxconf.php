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

    fwrite($postfix_map_fh, $domain . ' ' . $fullchain_file . ' ' . $key_file . "\n");
    if ($cert_row['wwwserveralias']) {
        fwrite($postfix_map_fh, 'www.' . $domain . ' ' . $fullchain_file . ' ' . $key_file . "\n");
    }
}

fclose($postfix_map_fh);
