<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class DovecotWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    protected function writeTLSConfig(SafeTempFile $fh, TLSConfig $config, string $prefix): void {
        $fh->writeLine($prefix . 'ssl_server_cert_file = ' . $config->fullChainFile);
        $fh->writeLine($prefix . 'ssl_server_key_file = ' . $config->keyFile);
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $this->writeTLSConfig($fh, $defaultConfig, '');
        }
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeLine("local_name $domain {");
        $this->writeTLSConfig($fh, $config, '  ');
        $fh->writeLine('}');
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verboseRun('systemctl reload dovecot');
    }
}
