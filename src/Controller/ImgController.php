<?php

namespace App\Controller;


use Cake\Filesystem\Folder;
use Cake\Http\Exception\NotFoundException;

/**
 * Class ImgController
 * Manage on-the-fly image resizing. All image calls are processed by this controller.
 *
 * @see ImageComponent
 */
class ImgController extends AppController
{
	public function initialize(): void
	{
		parent::initialize();
		$this->loadComponent('Image');
	}

	/**
     * This function explodes the passed path in param to retrieve the dimensions of the resized image.
     * It uses ImageComponent to resize the image.
     *
     * @param string $img Original image path.
     * @return \Cake\Http\Response Resized image path.
     */
    public function index($img)
    {
        preg_match("/.*(_([0-9]+)x([0-9]+)(@2x)?)\.[a-z0-9]+$/i", $img, $format);
        $dimensions = array($format[2], $format[3]);
        if (isset($format[4])) {
            $dimensions[0] *= 2;
            $dimensions[1] *= 2;
        }
        $path = IMAGES . str_replace($format[1], '', $img);
        $resized = IMAGES . RESIZED_DIR  . DS . pathinfo($img, PATHINFO_BASENAME);

        if (!file_exists($path)) {
            throw new NotFoundException();
        }

        if (!file_exists($resized)) {
            if (!file_exists(IMAGES . RESIZED_DIR)) {
                new Folder(IMAGES . RESIZED_DIR, true, 0777);
            }
            $this->Image->resize($path, $resized, (int) $dimensions[0], (int) $dimensions[1]);
        }

        return $this->response->withFile($resized);
    }
}
