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
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'config#getUsername', 'url' => '/username', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'Settings#createProvider', 'url' => '/provider', 'verb' => 'POST'],
		['name' => 'Settings#updateProvider', 'url' => '/provider/{providerId}', 'verb' => 'PUT'],
		['name' => 'Settings#deleteProvider', 'url' => '/provider/{providerId}', 'verb' => 'DELETE'],
		['name' => 'config#logoutProvider', 'url' => '/provider/{providerId}/logout', 'verb' => 'POST'],
		['name' => 'login#login', 'url' => '/login/{providerId}', 'verb' => 'GET'],
		['name' => 'login#code', 'url' => '/code', 'verb' => 'GET'],
	]
];