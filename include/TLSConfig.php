<?php
declare (strict_types=1);

class TLSConfig {
    private array $domains;
    private array $warnings;
    public readonly string $fullChainFile;
    public readonly string $keyFile;

    public function __construct(array $domains, string $fullChainFile, string $keyFile) {
        $this->domains = [];
        $this->warnings = [];
        $this->fullChainFile = $fullChainFile;
        $this->keyFile = $keyFile;

        foreach ($domains as $domain) {
            $domain = strtolower($domain);
            if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $this->domains[$domain] = $domain;
            } else {
                $this->warnings[] = "Skipping invalid domain: $domain\n";
            }
        }
    }

    public function uniqueKey(): string {
        return $this->fullChainFile . PHP_EOL . $this->keyFile;
    }

    public function hash(): string {
        $hashsrc = $this->uniqueKey() . PHP_EOL . filemtime($this->fullChainFile) . PHP_EOL . filemtime($this->keyFile);
        return hash('sha3-512', $hashsrc);
    }

    public function append(TLSConfig $other): void {
        if ($this->uniqueKey() !== $other->uniqueKey()) {
            throw new InvalidArgumentException("Cannot append TLSConfig with different files");
        }

        foreach ($other->domains as $domain) {
            $this->domains[$domain] = $domain;
        }
    }

    public function getDomains(): array {
        return array_values($this->domains);
    }

    public function getWarnings(): array {
        return $this->warnings;
    }

    public function clearWarnings(): void {
        $this->warnings = [];
    }
}
