<?php

declare(strict_types=1);

namespace App\View;

use Cake\View\View;

class AppView extends View
{
    public function initialize(): void
    {
        $this->loadHelper('Form', ['templates' => 'app_templates', 'className' => 'BootstrapForm']);
        $this->loadHelper('Html', ['className' => 'AjaxHtml']);
        $this->loadHelper('Paginator', ['templates' => 'app_templates']);
        $this->loadHelper('Authentication.Identity');
    }
}
