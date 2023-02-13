<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Setting extends Entity
{
    public function _getFromMp3()
    {
        return in_array('mp3', explode(',', $this->convert_from), true);
    }

    public function _getFromOgg()
    {
        return in_array('ogg', explode(',', $this->convert_from), true);
    }

    public function _getFromFlac()
    {
        return in_array('flac', explode(',', $this->convert_from), true);
    }
}
