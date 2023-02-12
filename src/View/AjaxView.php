<?php
declare(strict_types=1);

namespace App\View;

class AjaxView extends AppView
{
	protected $layout = 'ajax';

	public function initialize(): void
	{
		parent::initialize();
		$this->setResponse($this->response->withType('application/json'));
	}
}
