<?php

require_once 'tlswriter.php';

class MultiTLSWriter implements ITLSWriter {
    private readonly array $writers;

    public function __construct(array $writers) {
        $this->writers = $writers;
    }

    public function add(array $domains, string $fullchain_file, string $key_file): void {
        foreach ($this->writers as $writer) {
            $writer->add($domains, $fullchain_file, $key_file);
        }
    }

    public function save(): void {
        foreach ($this->writers as $writer) {
            $writer->save();
        }
    }
}
