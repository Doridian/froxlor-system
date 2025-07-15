<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class PureFTPDWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/pure-ftpd/tls_server_sni_maps');
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $data = 'action:strict' . PHP_EOL .
                'cert_file:' . $config->fullChainFile . PHP_EOL .
                'key_file:' . $config->keyFile . PHP_EOL .
                'end' . PHP_EOL;
        $fh->writeLine($domain . ' ' . base64_encode($data));
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $this->writeConfigDomain($fh, $defaultConfig, '*');
        }
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verboseRun('postmap /etc/pure-ftpd/tls_server_sni_maps');
    }
}
