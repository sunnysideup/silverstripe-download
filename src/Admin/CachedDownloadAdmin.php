<?php

namespace Sunnysideup\Download\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\Download\Control\Model\CachedDownload;

class CachedDownloadAdmin extends ModelAdmin
{
    private static $managed_models = [
        CachedDownload::class,
    ];

    private static $url_segment = 'downloads';

    private static $menu_title = 'Downloads';

}
