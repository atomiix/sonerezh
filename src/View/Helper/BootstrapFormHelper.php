<?php

declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper\FormHelper;

class BootstrapFormHelper extends FormHelper
{
    public function control(string $fieldName, array $options = []): string
    {
        if (!isset($options['templateVars'])) {
            $options['templateVars'] = [];
        }

        if (isset($options['divClass'])) {
            $options['templateVars']['divClass'] = ' '.trim($options['divClass']);
            unset($options['divClass']);
        }
        if (isset($options['divId'])) {
            $options['templateVars']['divId'] = ' id="'.trim($options['divId']).'"';
            unset($options['divId']);
        }
        if (isset($options['after'])) {
            $options['templateVars']['after'] = $options['after'];
            unset($options['after']);
        }

        return parent::control($fieldName, $options);
    }

    public function _confirm(string $okCode, string $cancelCode): string
    {
        $ok = explode('.', $okCode);
        $okCode = "$('form[name=".$ok[1]."]').submit();";
        return parent::_confirm($okCode, $cancelCode);
    }
}
