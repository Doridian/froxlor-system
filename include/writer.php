<?php

interface TLSWriter {
    public function write(array $domains, string $fullchain_file, string $key_file): void;
    public function save(): void;
}
