<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\VO_Federation\BackgroundJob;

use OCA\VO_Federation\FederatedGroupShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\ILogger;

/**
 * Class RetryJob
 *
 * Background job to re-send update of federated re-shares to the remote server in
 * case the server was not available on the first try
 *
 * @package OCA\FederatedFileSharing\BackgroundJob
 */
class RetryJob extends Job {
	private bool $retainJob = true;
	private FederatedGroupShareProvider $shareProvider;

	/** @var int max number of attempts to send the request */
	private int $maxTry = 20;

	/** @var int how much time should be between two tries (10 minutes) */
	private int $interval = 600;

	public function __construct(FederatedGroupShareProvider $shareProvider,
								ITimeFactory $time) {
		parent::__construct($time);
		$this->shareProvider = $shareProvider;
	}

	/**
	 * Run the job, then remove it from the jobList
	 */
	public function start(IJobList $jobList): void {
		if ($this->shouldRun($this->argument)) {
			parent::start($jobList);
			$jobList->remove($this, $this->argument);
			if ($this->retainJob) {
				$this->reAddJob($jobList, $this->argument);
			}
		}
	}

	protected function run($argument) {
		$share = $this->shareProvider->createShareObject($argument);	
		$parentShareId = (int)$argument['parent'];		
		$try = (int)$argument['try'] + 1;

		try {
			$shareId = $this->shareProvider->createFederatedShare($share, $parentShareId);
			$result = $shareId > 0;
		} catch (\Exception $e) {
			$result = false;
		}
		
		if ($result === true || $try > $this->maxTry) {
			$this->retainJob = false;
		}
	}

	/**
	 * Re-add background job with new arguments
	 */
	protected function reAddJob(IJobList $jobList, array $argument): void {
		$jobList->add(RetryJob::class,
			array_merge($argument, [
				'try' => (int)$argument['try'] + 1,
				'lastRun' => $this->time->getTime()
			])
		);
	}

	/**
	 * Test if it is time for the next run
	 */
	protected function shouldRun(array $argument): bool {
		$lastRun = (int)$argument['lastRun'];
		$try = (int)$argument['try'];
		return (($this->time->getTime() - $lastRun) > $this->interval) || $try === 0;
	}
}
