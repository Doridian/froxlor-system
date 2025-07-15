<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

define('PURE_FTPD_CERTFILE', '/etc/pure-ftpd/conf/CertFile');

class PureFTPDWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/pure-ftpd/tls_server_sni_maps');
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $data = 'action:strict' . PHP_EOL .
                'cert_file:' . $config->fullChainFile . PHP_EOL .
                'key_file:' . $config->keyFile . PHP_EOL .
                'end';
        $fh->writeLine($domain . ' ' . base64_encode($data));
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verboseRun('postmap /etc/pure-ftpd/tls_server_sni_maps');

        if ($defaultConfig) {
            $defaultData = $defaultConfig->fullChainFile . ',' .
                           $defaultConfig->keyFile . PHP_EOL;
            if (!file_exists(PURE_FTPD_CERTFILE) ||
                    file_get_contents(PURE_FTPD_CERTFILE) !== $defaultData) {
                file_put_contents(PURE_FTPD_CERTFILE, $defaultData);
                verboseRun('systemctl restart pure-ftpd-mysql');
            }
        } else if (file_exists(PURE_FTPD_CERTFILE)) {
            unlink(PURE_FTPD_CERTFILE);
            verboseRun('systemctl restart pure-ftpd-mysql');
        }
    }
}
