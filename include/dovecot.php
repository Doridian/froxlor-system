<?php

require_once 'writer.php';

class DovecotWriter extends TLSWriter {
    public function __construct() {
        parent::__construct('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    protected function writeHeader(): void {
        if (!empty($this->default_config)) {
            $this->file->writeln('ssl_cert = <' . $this->default_config->fullchain_file);
            $this->file->writeln('ssl_key = <' . $this->default_config->key_file);
        }
    }

    protected function writeConfigDomain(TLSConfig $config, string $domain): void {
        $this->file->writeln("local_name $domain {");
        $this->file->writeln('  ssl_cert = <' . $config->fullchain_file);
        $this->file->writeln('  ssl_key = <' . $config->key_file);
        $this->file->writeln('}');
    }

    public function postSave(): void {
        verbose_run('systemctl reload dovecot');
    }
}
