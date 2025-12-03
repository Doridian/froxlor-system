<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class DovecotWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $fh->writeLine('ssl_cert_file = ' . $defaultConfig->fullChainFile);
            $fh->writeLine('ssl_key_file = ' . $defaultConfig->keyFile);
        }
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeLine("local_name $domain {");
        $fh->writeLine('  ssl_cert_file = ' . $config->fullChainFile);
        $fh->writeLine('  ssl_key_file = ' . $config->keyFile);
        $fh->writeLine('}');
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verboseRun('systemctl reload dovecot');
    }
}
