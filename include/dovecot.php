<?php

require_once 'tmpfile.php';
require_once 'writer.php';

class DovecotWriter implements TLSWriter {
    private $file;

    public function __construct() {
        $this->file = new SafeTempFile('/etc/dovecot/conf.d/zzz-tls-sni.conf');
    }

    public function write(array $domains, string $fullchain_file, string $key_file): void {
        foreach ($domains as $domain) {
            $is_default = ($domain === '*');
            if ($is_default) {
                $prefix = '';
            } else {
                $prefix = '  ';
                $this->file->writeln("local_name $domain {");
            }
            $this->file->writeln($prefix . 'ssl_cert = <' . $fullchain_file);
            $this->file->writeln($prefix . 'ssl_key = <' . $key_file);
            if (!$is_default) {
                $this->file->writeln('}');
            }
        }
    }

    public function save(): void {
        $this->file->save();
    }
}
