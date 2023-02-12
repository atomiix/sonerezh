<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use App\Console\Command\ImportCommand;
use App\Middleware\InstallationMiddleware;
use App\Middleware\RedirectMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\ResolverInterface;
use Cake\Command\ServerCommand;
use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\EncryptedCookieMiddleware;
use Cake\Http\Middleware\SessionCsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\I18n\Middleware\LocaleSelectorMiddleware;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Router;
use Cake\Utility\Security;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface, AuthorizationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        } else {
            FactoryLocator::add(
                'Table',
                (new TableLocator())->allowFallbackClass(false)
            );
        }

        /*
         * Only try to load DebugKit in development mode
         * Debug Kit should not be installed on a production system
         */
        if (Configure::read('debug')) {
            $this->addPlugin('DebugKit');
        }

        // Load more plugins here
	}

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

			->add(new InstallationMiddleware())

			// Parse various types of encoded request bodies so that they are
			// available as array through $request->getData()
			// https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
			->add(new BodyParserMiddleware())
			->add(new AuthenticationMiddleware($this))
			->add(new AuthorizationMiddleware($this))
			->add(new RedirectMiddleware())
			->add(new LocaleSelectorMiddleware(['*']));

		if (Configure::read('installed')) {
			$middlewareQueue->insertBefore(InstallationMiddleware::class, new EncryptedCookieMiddleware(['CookieAuth'], Security::getSalt()));
			$middlewareQueue->insertBefore(InstallationMiddleware::class, new SessionCsrfProtectionMiddleware());
		}

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
    }

	public function console(CommandCollection $commands): CommandCollection
	{
		$commands->add('server', ServerCommand::class);
		$commands->add('import', ImportCommand::class);

		if (Configure::read('debug')) {
			return parent::console($commands);
		}

		return $commands;
	}


	/**
     * Bootstrapping for CLI application.
     *
     * That is when running commands.
     *
     * @return void
     */
    protected function bootstrapCli(): void
    {
        $this->addOptionalPlugin('Cake/Repl');
        $this->addOptionalPlugin('Bake');

        // Load more plugins here
    }

	public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
	{
		$loginUrl = Router::url([
			'prefix' => false,
			'plugin' => null,
			'controller' => 'Users',
			'action' => 'login',
		]);

		$service = new AuthenticationService([
			'unauthenticatedRedirect' => $loginUrl,
		]);

		$fields = [
			IdentifierInterface::CREDENTIAL_USERNAME => 'email',
			IdentifierInterface::CREDENTIAL_PASSWORD => 'password'
		];
		// Load the authenticators. Session should be first.
		$service->loadAuthenticator('Authentication.Session');
		// If the user is on the login page, check for a cookie as well.
		$service->loadAuthenticator('Authentication.Cookie', [
			'fields' => $fields,
			'loginUrl' => $loginUrl,
			'salt' => true,
		]);
		$service->loadAuthenticator('Authentication.Form', [
			'fields' => $fields,
			'loginUrl' => $loginUrl,
		]);

		// Load identifiers
		$service->loadIdentifier('Authentication.Password', compact('fields'));

		return $service;
	}

	public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
	{
		return new AuthorizationService(new class implements ResolverInterface {
			public function getPolicy($resource)
			{
				return new class () {

					public function __call(string $name, array $arguments) {
						[$user, $controller] = $arguments;

						return $user === null || $controller->isAuthorized($user->getOriginalData());
					}
				};
			}
		});
	}
}
