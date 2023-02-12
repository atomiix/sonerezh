<?php

namespace App\Controller\Component;

use App\Middleware\RedirectMiddleware;
use Authentication\Controller\Component\AuthenticationComponent as BaseAuthenticationComponent;

class AuthenticationComponent extends BaseAuthenticationComponent
{
	public function getLoginRedirect(): ?string
	{
		$redirect = $this->getController()
			->getRequest()
			->getAttribute('session')
			->consume(RedirectMiddleware::REDIRECT_KEY)
		;

		return $redirect ?? parent::getLoginRedirect();
	}
}
