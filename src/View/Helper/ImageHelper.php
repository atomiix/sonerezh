<?php

declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class ImageHelper extends Helper
{
    protected $helpers = ['Html'];
    public function resizedPath($path, $width = 0, $height = 0)
    {
        $extension = '.' . pathinfo($path, PATHINFO_EXTENSION);
        if ($extension != '.gif') {
            $path = str_replace($extension, "_" . $width . "x" . $height . $extension, $path);
        }
        return $path;
    }

    public function lazyload($path, $options = [])
    {
        $image = $this->Html->image($path, $options);
        return str_replace('src="', 'src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" onload="lzldhd(this)" data-src="', $image);
    }

    public function avatar($auth, $size = null)
    {
        $image = $auth->avatar;
        if (empty($image)) {
            $image = 'https://secure.gravatar.com/avatar/' . md5($auth->email) . '.png?r=x&s=' . $size;
        } else {
            $image = $this->resizedPath(AVATARS_DIR . DS . $auth->avatar, $size, $size);
        }
        return $image;
    }
}
