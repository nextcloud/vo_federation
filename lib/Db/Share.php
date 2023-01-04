<?php

namespace OCA\VO_Federation\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Share extends Entity implements JsonSerializable {
	protected $federatedGroupShareId;
	protected $instanceId;
	protected $cloudId;
	protected $accepted;
	protected $token;
	protected $notification;
	protected $try;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'federatedGroupShareId' => $this->federatedGroupShareId,
			'instanceId' => $this->instanceId,
			'cloudId' => $this->cloudId,
			'accepted' => $this->accepted,
			'token' => $this->token,
			'notification' => $this->notification,
			'try' => $this->try
		];
	}
}
