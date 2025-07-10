<?php
declare (strict_types=1);

interface ITLSConfigHolder {
    public function getConfigs(): array;
    public function getDefaultConfig(): ?TLSConfig;
}
