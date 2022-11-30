<?php

namespace OCA\VO_Federation\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class TrustedInstance extends Entity implements JsonSerializable {
	protected $providerId;
	protected $instanceUrl;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'providerId' => $this->providerId,
			'instanceUrl' => $this->instanceUrl
		];
	}
}
