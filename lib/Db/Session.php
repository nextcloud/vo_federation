<?php

namespace OCA\VO_Federation\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Session extends Entity implements JsonSerializable {
	protected $uid;
	protected $providerId;
	protected $idToken;
	protected $idTokenSub;
	protected $idTokenExp;
	protected $accessToken;
	protected $accessTokenExp;
	protected $refreshToken;
	protected $refreshTokenExp;
	protected $userinfoDisplayName;
	protected $lastSync;

	public function __construct() {
		$this->addType('lastSync', 'datetime');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'providerId' => $this->providerId,
			'idToken' => $this->idToken,
			'idTokenSub' => $this->idTokenSub,
			'idTokenExp' => $this->idTokenExp,
			'accessToken' => $this->accessToken,
			'accessTokenExp' => $this->accessTokenExp,
			'refreshToken' => $this->refreshToken,
			'refreshTokenExp' => $this->refreshTokenExp,
			'userinfoDisplayName' => $this->userinfoDisplayName,
			'lastSync' => $this->lastSync,
		];
	}
}
