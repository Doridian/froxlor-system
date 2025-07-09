<?php

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

echo "export FROXLOR_DB_USER='" . $sql['user'] . "';";
echo "export FROXLOR_DB_PASSWORD='" . $sql['password'] . "';";
echo "export FROXLOR_DB_NAME='" . $sql['db'] . "';";
echo "export FROXLOR_DB_HOST='" . $sql['host'] . "';";
