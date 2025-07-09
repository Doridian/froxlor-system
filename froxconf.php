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

while ($cert_row = $cert_res->fetch_assoc()) {
    $fullchain_file = SSL_DIR . '/' . $cert_row['domain'] . '_fullchain.pem';
    if (!file_exists($fullchain_file)) {
        continue;
    }

    $key_file = SSL_DIR . '/' . $cert_row['domain'] . '.key';
    if (!file_exists($key_file)) {
        continue;
    }
}
