<?php

interface ITLSConfigHolder {
    public function getConfigs(): array;
    public function getDefaultConfig(): ?TLSConfig;
}
