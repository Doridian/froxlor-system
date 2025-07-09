<?php

class TLSConfig {
    private array $domains;
    private bool $is_default;
    public readonly string $fullchain_file;
    public readonly string $key_file;

    public function __construct(array $domains, string $fullchain_file, string $key_file) {
        $this->domains = [];
        $this->is_default = false;
        $this->fullchain_file = $fullchain_file;
        $this->key_file = $key_file;

        foreach ($domains as $domain_raw) {
            $domain = strtolower(trim($domain_raw));
            if ($domain === '*') {
                $this->is_default = true;
                continue;
            }
            if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $this->domains[] = $domain;
            } else {
                echo "Skipping invalid domain: $domain_raw\n";
            }
        }
    }

    public function hash(): string {
        return $this->fullchain_file . '|' . $this->key_file;
    }

    public function append(TLSConfig $other): void {
        if ($this->fullchain_file !== $other->fullchain_file || $this->key_file !== $other->key_file) {
            throw new InvalidArgumentException("Cannot append TLSConfig with different files");
        }

        foreach ($other->domains as $domain) {
            if (!in_array($domain, $this->domains, true)) {
                $this->domains[] = $domain;
            }
        }

        if ($other->is_default) {
            $this->is_default = true;
        }
    }

    public function getDomains(): array {
        return $this->domains;
    }

    public function isDefault(): bool {
        return $this->is_default;
    }
}
