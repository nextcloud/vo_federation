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

namespace OCA\VO_Federation\Middleware;

use OCA\Files_Sharing\Controller\ShareAPIController;
use OCA\VO_Federation\Db\ShareMapper as VOShareMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use Psr\Log\LoggerInterface;

class ShareAPIMiddleware extends Middleware {
	private VOShareMapper $voShareMapper;
	private LoggerInterface $logger;

	public function __construct(VOShareMapper $voShareMapper, LoggerInterface $logger) {
		$this->voShareMapper = $voShareMapper;
		$this->logger = $logger;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param Response $response
	 * @return Response
	 */
	public function beforeOutput($controller, $methodName, $output) {
		if (!($controller instanceof ShareAPIController)) {
			return $output;
		}

		if ($methodName !== 'getShares') {
			return $output;
		}

		try {
			$ocs = json_decode($output, true);
			// Local group shares ids associated with federated group shares
			$localGroupShareIds = $this->voShareMapper->getLocalGroupShareIds();
			
			if ($ocs['ocs']['meta']['status'] === 'ok') {
				$ocs['ocs']['data'] = array_filter($ocs['ocs']['data'], function ($share) use ($localGroupShareIds) {
					$shareId = $share['id'];
					$shareType = $share['share_type'];
		
					if ($shareType === \OCP\Share\IShare::TYPE_GROUP && in_array($shareId, $localGroupShareIds)) {
						return false;
					}
					return true;
				});
		
				$ocs['ocs']['data'] = array_values($ocs['ocs']['data']);
			}
			return json_encode($ocs, JSON_HEX_TAG);
		} catch (\Exception $e) {
		}

		return $output;
	}
}
