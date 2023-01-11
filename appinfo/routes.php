<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\VO_Federation\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'routes' => [
		['name' => 'settings#createProvider', 'url' => '/provider', 'verb' => 'POST'],
		['name' => 'settings#updateProvider', 'url' => '/provider/{providerId}', 'verb' => 'PUT'],
		['name' => 'settings#deleteProvider', 'url' => '/provider/{providerId}', 'verb' => 'DELETE'],
		['name' => 'settings#logoutProvider', 'url' => '/provider/{providerId}/logout', 'verb' => 'POST'],
		['name' => 'login#login', 'url' => '/login/{providerId}', 'verb' => 'GET'],
		['name' => 'login#code', 'url' => '/code', 'verb' => 'GET'],
		//['name' => 'share#sharees', 'url' => '/sharees', 'verb' => 'GET'],
		['name' => 'avatar#getAvatarDark', 'url' => '/avatar/{providerId}/{size}/dark', 'verb' => 'GET'],
		['name' => 'avatar#getAvatar', 'url' => '/avatar/{providerId}/{size}', 'verb' => 'GET'],
		['name' => 'avatar#deleteAvatar', 'url' => '/avatar/{providerId}', 'verb' => 'DELETE'],
		['name' => 'avatar#postCroppedAvatar', 'url' => '/avatar/{providerId}/cropped', 'verb' => 'POST'],
		['name' => 'avatar#getTmpAvatar', 'url' => '/avatar/tmp', 'verb' => 'GET'],
		['name' => 'avatar#postAvatar', 'url' => '/avatar/{providerId}', 'verb' => 'POST'],
		]
];
