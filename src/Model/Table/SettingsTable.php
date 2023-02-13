<?php

declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class SettingsTable extends Table
{
    public function initialize(array $config): void
    {
        $this->hasMany('Rootpaths', ['saveStrategy' => 'replace']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator->boolean('enable_auto_conv', __('Something went wrong!'))
            ->inList('convert_to', ['mp3', 'ogg'], __('Wrong conversion destination!'))
            ->add('convert_to', 'custom', [
                'rule' => [$this, 'validConversion'],
                'message' => __('Wrong conversion destination! Make sure you are not trying to convert MP3 to MP3, or OGG to OGG.'),
            ])
            ->boolean('enable_mail_notification', __('Something went wrong!'));
    }

    public function validConversion($field, $context)
    {
        return $field === 'mp3' && (bool) $context['data']['from_mp3'] === false
            || $field === 'ogg' && (bool) $context['data']['from_ogg'] === false
        ;
    }

    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        $convertfrom = [];
        if (isset($data['from_mp3']) && $data['from_mp3']) {
            $convertfrom[] = 'mp3';
        }
        if (isset($data['from_ogg']) && $data['from_ogg']) {
            $convertfrom[] = 'ogg';
        }
        if (isset($data['from_flac']) && $data['from_flac']) {
            $convertfrom[] = 'flac';
        }
        $data['convert_from'] = implode(',', $convertfrom);
    }
}
