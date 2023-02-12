<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\Session;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InstallationMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$controller = $request->getAttribute('params')['controller'];

		if (Configure::read('installed') === null) {
			/** @var Session $session */
			$session = $request->getAttribute('session');
			$session->destroy();
			if ($controller !== 'Installers') {
				return (new Response())->withStatus(302)->withLocation(Router::url(['controller' => 'installers', 'action' => 'index']));
			}
		} else if ($controller === 'Installers') {
			$request->getAttribute('flash')->info(__('Sonerezh is already installed. Remove or rename config/app_config.php to run the installation again.'));
			return (new Response())->withStatus(302)->withLocation(Router::url(['controller' => 'songs', 'action' => 'index']));
		}

		return $handler->handle($request);
	}
}
