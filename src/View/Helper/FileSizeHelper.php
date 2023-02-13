<?php

declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class FileSizeHelper extends Helper
{
    public $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    public function humanize($bytes)
    {
        if ($bytes <= 0) {
            return '0';
        } else {
            $factor = (int)(log($bytes, 1024));
            return sprintf("%.2f", $bytes / 1024** $factor) . $this->units[$factor];
        }
    }
}
