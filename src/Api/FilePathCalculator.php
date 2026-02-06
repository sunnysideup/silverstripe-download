<?php

namespace Sunnysideup\Download\Api;

use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class FilePathCalculator
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * returns the full path to the file
     */
    public static function get_path(File $file): string
    {
        $path = Controller::join_links(ASSETS_PATH, $file->getFilename());
        if (! file_exists($path)) {
            $path = Controller::join_links(PUBLIC_PATH, $file->getSourceURL(true));
            if (! file_exists($path)) {
                $path = str_replace('public/assets/', 'public/assets/.protected/', $path);
                if (! file_exists($path)) {
                    $path = '';
                }
            }
        }
        return $path;
    }
}
