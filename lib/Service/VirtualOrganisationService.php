<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Service;

use OCA\VO_Federation\Backend\GroupBackend;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

class VirtualOrganisationService {
	/** @var IConfig */
	private $config;

	private $groupManager;
	private $userManager;
	private $voGroupBackend;

	public function __construct(IConfig $config, IGroupManager $groupManager, IUserManager $userManager, GroupBackend $voGroupBackend) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->voGroupBackend = $voGroupBackend;
	}

	public function addVOUser($gid, $userId, $displayName, $aai) {
		$this->voGroupBackend->createVOGroup($gid, $displayName, $aai);
		$group = $this->groupManager->get($gid);
		$group->addUser($this->userManager->get($userId));
	}
}
