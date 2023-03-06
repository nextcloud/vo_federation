<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Guillaume Virlet <github@virlet.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\VO_Federation\Federation;

use OCP\Federation\CloudId;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdResolver;

class CloudIdResolver implements ICloudIdResolver {
	/**
	 * @param string $cloudId
	 * @return ICloudId
	 * @throws \InvalidArgumentException
	 */
	public function resolveCloudId(string $cloudId): ICloudId {
		// TODO magic here to get the url and user instead of just splitting on @

		if (!$this->isValidCloudId($cloudId)) {
			throw new \InvalidArgumentException('Invalid cloud id');
		}

		$id = $this->fixRemoteURL($cloudId);

		$lastValidAtPos = strrpos($id, '@');

		if ($lastValidAtPos !== false) {
			$user = substr($id, 0, $lastValidAtPos);
			$remote = substr($id, $lastValidAtPos + 1);

			if (!empty($user) && !empty($remote)) {
				return new CloudId($id, $user, $remote);
			}
		}
		throw new \InvalidArgumentException('Invalid cloud id');
	}

	/**
	 * @param string $cloudId
	 * @return bool
	 */
	public function isValidCloudId(string $cloudId): bool {
		if (strpos($cloudId, '@') !== false) {
			$urnPos = strpos($cloudId, 'urn:');
			if ($urnPos !== false && $urnPos <= 1) {
				return true;
			};
		}

		return false;
	}

	/**
	 * Strips away a potential file names and trailing slashes:
	 * - http://localhost
	 * - http://localhost/
	 * - http://localhost/index.php
	 * - http://localhost/index.php/s/{shareToken}
	 *
	 * all return: http://localhost
	 *
	 * @param string $remote
	 * @return string
	 */
	protected function fixRemoteURL(string $remote): string {
		$remote = str_replace('\\', '/', $remote);
		if ($fileNamePosition = strpos($remote, '/index.php')) {
			$remote = substr($remote, 0, $fileNamePosition);
		}
		$remote = rtrim($remote, '/');

		return $remote;
	}
}
