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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

return [
	'routes' => [
		['name' => 'settings#createProvider', 'url' => '/provider', 'verb' => 'POST'],
		['name' => 'settings#updateProvider', 'url' => '/provider/{providerId}', 'verb' => 'PUT'],
		['name' => 'settings#deleteProvider', 'url' => '/provider/{providerId}', 'verb' => 'DELETE'],
		['name' => 'settings#logoutProvider', 'url' => '/provider/{providerId}/logout', 'verb' => 'POST'],

		['name' => 'login#login', 'url' => '/login/{providerId}', 'verb' => 'GET'],
		['name' => 'login#code', 'url' => '/code', 'verb' => 'GET'],
		
		['name' => 'avatar#getAvatarDark', 'url' => '/avatar/{providerId}/{size}/dark', 'verb' => 'GET'],
		['name' => 'avatar#getAvatar', 'url' => '/avatar/{providerId}/{size}', 'verb' => 'GET'],
		['name' => 'avatar#deleteAvatar', 'url' => '/avatar/{providerId}', 'verb' => 'DELETE'],
		['name' => 'avatar#postCroppedAvatar', 'url' => '/avatar/{providerId}/cropped', 'verb' => 'POST'],
		['name' => 'avatar#getTmpAvatar', 'url' => '/avatar/tmp', 'verb' => 'GET'],
		['name' => 'avatar#postAvatar', 'url' => '/avatar/{providerId}', 'verb' => 'POST'],
	]
];
