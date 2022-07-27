<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

class VirtualOrganisationService {
	/** @var IConfig */
	private $config;

	private $groupManager;
	private $userManager;

	public function __construct(IConfig $config, IGroupManager $groupManager, IUserManager $userManager) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	public function addVOUser($gid, $userId, $clientId) {
		$gid = mb_substr($gid, 0, 64);
		$group = $this->groupManager->createGroup($gid);
		$group->addUser($this->userManager->get($userId));
	}
}
