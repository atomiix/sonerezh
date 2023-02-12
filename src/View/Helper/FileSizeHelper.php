<?php

namespace App\View\Helper;

use Cake\View\Helper;

class FileSizeHelper extends Helper
{

    public $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

    public function humanize($bytes)
    {
        if ($bytes <= 0) {
            return '0';
        } else {
            $factor = (int)(log($bytes, 1024));
            return sprintf("%.2f", $bytes / pow(1024, $factor)) . $this->units[$factor];
        }
    }

}
