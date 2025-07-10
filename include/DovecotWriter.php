<?php

require_once 'ConfigWriter.php';

class DovecotWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $fh->writeln('ssl_cert = <' . $defaultConfig->fullchain_file);
            $fh->writeln('ssl_key = <' . $defaultConfig->key_file);
        }
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeln("local_name $domain {");
        $fh->writeln('  ssl_cert = <' . $config->fullchain_file);
        $fh->writeln('  ssl_key = <' . $config->key_file);
        $fh->writeln('}');
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verbose_run('systemctl reload dovecot');
    }
}
