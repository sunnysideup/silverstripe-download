<?php

namespace Sunnysideup\Download\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\Download\Model\CachedDownload;

/**
 * Class \Sunnysideup\Download\Admin\CachedDownloadAdmin
 */
class CachedDownloadAdmin extends ModelAdmin
{
    private static $managed_models = [
        CachedDownload::class,
    ];

    private static $url_segment = 'downloads';

    private static $menu_title = 'Downloads';
}
