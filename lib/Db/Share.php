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
