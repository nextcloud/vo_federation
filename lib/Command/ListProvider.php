<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\VO_Federation\Command;

use OC\Core\Command\Base;
use OCA\VO_Federation\Db\ProviderMapper;
use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Output\OutputInterface;

class ListProvider extends Base {

	public function __construct(
		private ProviderService $providerService,
		private ProviderMapper $providerMapper
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('vo_federation:provider:list')
			->setDescription('List Community AAIs or show details given an identifier')
			->addArgument('identifier', InputArgument::OPTIONAL, 'Administrative identifier name of the provider in the setup');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$outputFormat = $input->getOption('output') ?? 'table';
		$identifier = $input->getArgument('identifier');

		if ($identifier === null) {
			return $this->listProviders($input, $output);
		}

		try {
			$provider = $this->providerMapper->findProviderByIdentifier($identifier);
		} catch (DoesNotExistException $e) {
			$output->writeln('Provider not found');
			return -1;
		}
		$provider = $this->providerService->getProviderWithSettings($provider->getId());
		if ($outputFormat === 'json') {
			$output->writeln(json_encode($provider, JSON_THROW_ON_ERROR));
			return 0;
		}

		if ($outputFormat === 'json_pretty') {
			$output->writeln(json_encode($provider, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
			return 0;
		}

		$provider = array_merge($provider, $provider['settings']);
		unset($provider['settings']);
		$provider['trustedInstances'] = implode(', ', $provider['trustedInstances']);

		$table = new Table($output);
		$table->setHeaders(array_keys($provider));
		$table->addRow($provider);
		$table->setVertical(true);
		$table->render();
		return 0;

	}

	private function listProviders(InputInterface $input, OutputInterface $output) {
		$outputFormat = $input->getOption('output') ?? 'table';
		$providers = $this->providerMapper->getProviders();

		if ($outputFormat === 'json') {
			$output->writeln(json_encode($providers, JSON_THROW_ON_ERROR));
			return 0;
		}

		if ($outputFormat === 'json_pretty') {
			$output->writeln(json_encode($providers, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
			return 0;
		}

		if (count($providers) === 0) {
			$output->writeln('No providers configured');
			return 0;
		}

		$table = new Table($output);
		$table->setHeaders(['ID', 'Identifier', 'Discovery endpoint', 'Client ID']);
		$providers = array_map(function ($provider) {
			return [
				$provider->getId(),
				$provider->getIdentifier(),
				$provider->getDiscoveryEndpoint(),
				$provider->getClientId()
			];
		}, $providers);
		$table->setRows($providers);
		$table->render($providers);
		return 0;
	}
}
