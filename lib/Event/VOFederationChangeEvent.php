<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 *
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\VO_Federation\Event;

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
