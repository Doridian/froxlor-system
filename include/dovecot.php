<?php

require_once 'writer.php';

class DovecotWriter extends TLSWriter {
    public function __construct() {
        parent::__construct('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    protected function writeHeader(SafeTempFile $fh): void {
        if (!empty($this->default_config)) {
            $fh->writeln('ssl_cert = <' . $this->default_config->fullchain_file);
            $fh->writeln('ssl_key = <' . $this->default_config->key_file);
        }
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeln("local_name $domain {");
        $fh->writeln('  ssl_cert = <' . $config->fullchain_file);
        $fh->writeln('  ssl_key = <' . $config->key_file);
        $fh->writeln('}');
    }

    public function postSave(): void {
        verbose_run('systemctl reload dovecot');
    }
}
