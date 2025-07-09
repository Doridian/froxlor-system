#!/usr/bin/env php
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

function die_error($message) {
    echo "Error: $message\n";
    exit(1);
}

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or die_error('Database connection error '. mysqli_connect_error());

function get_setting($group, $name) {
    global $db;
    $stmt = $db->prepare('SELECT value FROM panel_settings WHERE settinggroup = ? AND varname = ? LIMIT 1');
    $stmt->bind_param('ss', $group, $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die_error("Setting $group.$name not found in panel_settings, please set it in the Froxlor settings.");
    }
    $row = $result->fetch_assoc();
    $value = $row['value'];
    if (empty($value)) {
        die_error("Setting $group.$name is empty, please set it in the Froxlor settings.");
    }
    $stmt->close();
    return $value;
}

$ssl_dir = rtrim(get_setting('system', 'customer_ssl_path'), '/') . '/';
$fqdn = strtolower(trim(get_setting('system', 'hostname')));
