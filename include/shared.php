<?php
declare (strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

function die_error(string $message): void {
    echo "Error: $message\n";
    exit(1);
}

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or die_error('Database connection error '. mysqli_connect_error());

function get_setting(string $group, string $name): string {
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
$fqdn = get_setting('system', 'hostname');

function fullchain_from_domain(string $domain): string {
    global $ssl_dir;
    return $ssl_dir . $domain . '_fullchain.pem';
}

function key_from_domain(string $domain): string {
    global $ssl_dir;
    return $ssl_dir . $domain . '.key';
}

function verbose_run(string $command): void {
    echo "Running: $command\n";
    passthru($command);
}

$fqdn_fullchain_file = fullchain_from_domain($fqdn);
$fqdn_key_file = key_from_domain($fqdn);
