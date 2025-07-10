<?php
declare (strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

function dieError(string $message): void {
    echo "Error: $message" . PHP_EOL;
    exit(1);
}

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or dieError('Database connection error '. mysqli_connect_error());

function getSetting(string $group, string $name): string {
    global $db;
    $stmt = $db->prepare('SELECT value FROM panel_settings WHERE settinggroup = ? AND varname = ? LIMIT 1');
    $stmt->bind_param('ss', $group, $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        dieError("Setting $group.$name not found in panel_settings, please set it in the Froxlor settings.");
    }
    $row = $result->fetch_assoc();
    $value = $row['value'];
    if (empty($value)) {
        dieError("Setting $group.$name is empty, please set it in the Froxlor settings.");
    }
    $stmt->close();
    return $value;
}

$ssl_dir = rtrim(getSetting('system', 'customer_ssl_path'), '/') . '/';
$fqdn = getSetting('system', 'hostname');

function verboseRun(string $command): void {
    echo "Running: $command" . PHP_EOL;
    passthru($command);
}
