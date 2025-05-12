<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\VO_Federation\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\VO_Federation\Db\ProviderMapper;

use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\Db\DoesNotExistException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteProvider extends Base {

	public function __construct(
		private ProviderMapper $providerMapper,
		private ProviderService $providerService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('vo_federation:provider:delete')
			->setDescription('Delete a Community AAI')
			->addArgument('identifier', InputArgument::REQUIRED, 'Administrative identifier name of the provider to delete');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		try {
			$identifier = $input->getArgument('identifier');
			try {
				$provider = $this->providerMapper->findProviderByIdentifier($identifier);
			} catch (DoesNotExistException $e) {
				$output->writeln('Provider not found.');
				return -1;
			}
			$this->providerService->deleteProvider($provider->getId());
			$output->writeln('"' . $provider->getIdentifier() . '" has been deleted.');
		} catch (Exception $e) {
			$output->writeln($e->getMessage());
			return -1;
		}
		return 0;
	}
}
