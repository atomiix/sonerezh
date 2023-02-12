<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes) {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

	$routes->scope('/', function (RouteBuilder $builder) {
		$builder->connect('/install', array('controller' => 'installers', 'action' => 'index'));
		$builder->connect('/docker-install', array('controller' => 'installers', 'action' => 'docker'));
	});

    $routes->scope('/', function (RouteBuilder $builder) {
		$builder->connect('/', array('controller' => 'songs', 'action' => 'index'));

		$builder->connect('/login', array('controller' => 'users', 'action' => 'login'));
		$builder->connect('/logout', array('controller' => 'users', 'action' => 'logout'));

		$builder->connect('/api/*', array('controller' => 'api'));

		$builder->connect('/playlists/{action}/{id}', array('controller' => 'playlists'), array('pass' => array('id')));
		$builder->connect('/playlists/add', array('controller' => 'playlists', 'action' => 'add'));
		$builder->connect('/playlists/*', array('controller' => 'playlists', 'action' => 'index'));

		$builder->connect('/users', array('controller' => 'users', 'action' => 'index'));

		$builder->connect('/settings', array('controller' => 'settings', 'action' => 'index'));

		$builder->connect('/img/**', array('controller' => 'img', 'action' => 'index'));

		$builder->connect('/{action}', array('controller' => 'songs'));

		$builder->fallbacks();
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder) {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
