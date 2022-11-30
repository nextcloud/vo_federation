<?php

use OCP\EventDispatcher\Event;

class VOFederationChangeEvent extends Event {

    private int $providerId;
    private array $trustedInstances;

    public function __construct(int $providerId, array $trustedInstances) {
        parent::__construct();
        $this->providerId = $providerId;
        $this->trustedInstances = $trustedInstances;
    }

    public function getProviderId(): int {
        return $this->providerId;
    }

    public function getTrustedInstances(): array {
        return $this->trustedInstances;
    }
}
