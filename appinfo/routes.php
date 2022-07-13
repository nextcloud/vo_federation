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
		['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
        ['name' => 'config#doOauthStep1', 'url' => '/oauth-step1', 'verb' => 'GET'],
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
	]
];
