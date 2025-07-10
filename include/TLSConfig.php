<?php
declare (strict_types=1);

class TLSConfig {
    private array $domains;
    private array $warnings;
    public readonly string $fullchain_file;
    public readonly string $key_file;

    public function __construct(array $domains, string $fullchain_file, string $key_file) {
        $this->domains = [];
        $this->warnings = [];
        $this->fullchain_file = $fullchain_file;
        $this->key_file = $key_file;

        foreach ($domains as $domain) {
            if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $this->domains[] = $domain;
            } else {
                $this->warnings[] = "Skipping invalid domain: $domain\n";
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
    }

    public function getDomains(): array {
        return $this->domains;
    }

    public function getWarnings(): array {
        return $this->warnings;
    }

    public function clearWarnings(): void {
        $this->warnings = [];
    }
}
