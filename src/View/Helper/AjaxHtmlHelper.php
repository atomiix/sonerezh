<?php
declare(strict_types=1);

namespace App\View\Helper;


use Cake\View\Helper\HtmlHelper;

class AjaxHtmlHelper extends HtmlHelper
{
    public function link($title, $url = null, array $options = []): string
    {
        if ($options['confirm'] ?? false) {
            $options['data-confirm'] = $options['confirm'];
			unset($options['confirm']);
        }

        return parent::link($title, $url, $options);
    }

}
