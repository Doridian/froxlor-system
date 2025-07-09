<?php

require_once 'tmpfile.php';
require_once 'writer.php';

class PostfixWriter implements TLSWriter {
    private $file;

    public function __construct() {
        $this->file = new SafeTempFile('/etc/postfix/tls_server_sni_maps', 0640);
    }

    public function write(array $domains, string $fullchain_file, string $key_file): void {
        foreach ($domains as $domain) {
            $this->file->writeln($domain . ' ' . $key_file . ' ' . $fullchain_file);
        }
    }

    public function save(): void {
        $this->file->save();
    }
}
