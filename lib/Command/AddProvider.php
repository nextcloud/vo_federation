<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\VO_Federation\Command;

use OC\Core\Command\Base;
use OCA\VO_Federation\Db\ProviderMapper;
use OCA\VO_Federation\Db\Provider;
use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddProvider extends Base {

	public function __construct(
		private ProviderService $providerService,
		private ProviderMapper $providerMapper
	) {
		parent::__construct();
	}

	private const EXTRA_OPTIONS = [
		'authorization-endpoint' => [
			'shortcut' => null, 'mode' => InputOption::VALUE_REQUIRED, 'setting_key' => ProviderService::SETTING_AUTHORIZATION_ENDPOINT,
			'description' => 'Authorization endpoint',
		],
		'token-endpoint' => [
			'shortcut' => null, 'mode' => InputOption::VALUE_REQUIRED, 'setting_key' => ProviderService::SETTING_TOKEN_ENDPOINT,
			'description' => 'Token endpoint',
		],
		'jwks-endpoint' => [
			'shortcut' => null, 'mode' => InputOption::VALUE_REQUIRED, 'setting_key' => ProviderService::SETTING_JWKS_ENDPOINT,
			'description' => 'JWKS endpoint',
		],
		'userinfo-endpoint' => [
			'shortcut' => null, 'mode' => InputOption::VALUE_REQUIRED, 'setting_key' => ProviderService::SETTING_USERINFO_ENDPOINT,
			'description' => 'Userinfo endpoint',
		],
		'extra-claims' => [
			'shortcut' => null, 'mode' => InputOption::VALUE_REQUIRED, 'setting_key' => ProviderService::SETTING_EXTRA_CLAIMS,
			'description' => 'Extra claims to request when getting tokens',
		]
	];

	protected function configure() {
		$this
			->setName('vo_federation:provider:add')
			->setDescription('Add a Community AAI')
			->addArgument('identifier', InputArgument::REQUIRED, 'Administrative identifier name of the provider in the setup')
			->addOption('clientid', 'c', InputOption::VALUE_REQUIRED, 'OpenID client identifier')
			->addOption('clientsecret', 's', InputOption::VALUE_REQUIRED, 'OpenID client secret')
			->addOption('discoveryuri', 'd', InputOption::VALUE_REQUIRED, 'OpenID discovery endpoint uri')
			->addOption('scope', 'o', InputOption::VALUE_REQUIRED, 'OpenID requested value scopes, if not set defaults to "openid email profile"')
			->addOption('mapping-uid', null, InputOption::VALUE_REQUIRED, 'Attribute mapping of the user id')
			->addOption('mapping-display-name', null, InputOption::VALUE_REQUIRED, 'Attribute mapping of the display name')
			->addOption('mapping-groups', null, InputOption::VALUE_REQUIRED, 'Attribute mapping of the groups')
			->addOption('regex-pattern', null, InputOption::VALUE_REQUIRED, 'Regex pattern to match the group display name')
			->addOption('trusted-instance', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Trusted instance to be added to the provider, can be used multiple times', []);
		foreach (self::EXTRA_OPTIONS as $name => $option) {
			$this->addOption($name, $option['shortcut'], $option['mode'], $option['description']);
		}
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$identifier = $input->getArgument('identifier');
		$clientId = $input->getOption('clientid');
		$clientSecret = $input->getOption('clientsecret');
		$discoveryEndpoint = $input->getOption('discoveryuri');
		$scope = $input->getOption('scope');
		$uidClaim  = $input->getOption('mapping-uid');
		$displayNameClaim = $input->getOption('mapping-display-name');
		$groupsClaim = $input->getOption('mapping-groups');
		$groupsRegex = $input->getOption('regex-pattern');
		$trustedInstances = $input->getOption('trusted-instance');

		$settings = [];
		foreach (self::EXTRA_OPTIONS as $name => $option) {
			if (($value = $input->getOption($name)) !== null) {
				$settings[$option['setting_key']] = $value;
			} else if ($name === 'extra-claims') {
				$settings[$option['setting_key']] = "";
			}
		}

		if ($this->providerService->getProviderByIdentifier($identifier) !== null) {
			$output->writeln('Provider with the given identifier already exists');
			return -1;
		}

		try {
			$provider = new Provider();
			$provider->setIdentifier($identifier);
			$provider->setClientId($clientId);
			$provider->setClientSecret($clientSecret);
			$provider->setDiscoveryEndpoint($discoveryEndpoint);
			$provider->setScope($scope);
			$provider->setSettings($settings);

			$provider->setUidClaim($uidClaim);
			$provider->setDisplayNameClaim($displayNameClaim);
			$provider->setGroupsClaim($groupsClaim);
			$provider->setGroupsRegex($groupsRegex);

			$provider = $this->providerMapper->insert($provider);
			$this->providerService->createOrUpdateTrustedInstances($provider->getId(), $trustedInstances);
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return -1;
		}
		return 0;
	}
}
