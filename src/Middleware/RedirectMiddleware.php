<?php
declare(strict_types=1);

namespace App\Middleware;

use Authentication\Authenticator\UnauthenticatedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RedirectMiddleware implements MiddlewareInterface
{
	public const REDIRECT_KEY = 'redirect';
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		try {
			return $handler->handle($request);
		} catch (UnauthenticatedException $exception) {
			$request->getAttribute('session')->write(self::REDIRECT_KEY, $request->getUri()->getPath());
			throw $exception;
		}
	}
}
