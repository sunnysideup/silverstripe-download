<?php

namespace Sunnysideup\Download\Control\Model;

use Closure;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Flushable;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 *
 * @property string $Title
 * @property string $Link
 * @property int $ProductCount
 */
class CachedDownload extends DataObject implements Flushable
{
    public static function file_path(string $fileName): string
    {
        return Controller::join_links(Director::baseFolder(), PUBLIC_DIR, $fileName);
    }
    public static function flush()
    {
        if(DB::get_schema()->hasTable('CachedDownload')) {
            $list = self::get();
            foreach ($list as $item) {
                $item->delete();
            }
        }
    }

    private static $max_age_in_minutes = 60;
    private static $table_name = 'CachedDownload';

    private static $db = [
        'Title' => 'Varchar(255)',
        'MyLink' => 'Varchar(255)',
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];


    //######################
    //## Names Section
    //######################

    private static $singular_name = 'Cached Download';

    private static $plural_name = 'Cached Download';

    private static $summary_fields = [
        'Title' => 'Type',
        'MyLink' => 'Link',
        'LastEdited.Ago' => 'Last updated',
    ];

    public static function inst(string $link, ?string $title = ''): self
    {
        $obj = self::get()->filter(['MyLink' => $link])->first();
        if (!$obj) {
            $obj = self::create();
            $obj->MyLink = $link;
            $obj->Title = $title ?: $link;
            $obj->write();
        }

        return $obj;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [

                ReadonlyField::create(
                    'LastEditedNice',
                    'Cached created',
                ),

                LiteralField::create(
                    'CreateNewOne',
                    '<p class="message warning">Create a new cache by writing this one.</p>',
                ),

                LiteralField::create(
                    'ReviewCurrentOne',
                    '<p class="message good"><a href="' . $this->MyLink . '">Review current version</a></p>',
                ),
            ]
        );

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->deleteFile();
    }

    public function getLastEditedNice()
    {
        return DBField::create_field(DBDatetime::class, $this->LastEdited)->ago();
    }

    public function WarmCache(string $data): string
    {
        file_put_contents($this->getFilePath(), $data);
        return file_get_contents($this->getFilePath());
    }

    protected function getAbsoluteLink()
    {
        return Controller::join_links(Director::absoluteBaseURL(), $this->MyLink);
    }


    /**
     * Returns file details.
     * If the cache is expired, it redoes (warms) the cache.
     * Otherwise straight from the cached file
     *
     * @return string
     */
    public function getData(Closure $callBackIfEmpty): string
    {
        $maxCacheAge = strtotime('now') - ($this->Config()->max_age_in_minutes * 60);
        if (strtotime((string) $this->LastEdited) > $maxCacheAge) {
            $path = $this->getFilePath();
            if (file_exists($path)) {
                $timeChange = filemtime($path);
                if ($timeChange > $maxCacheAge) {
                    return file_get_contents($path);
                }
            }
        }
        $data = $callBackIfEmpty();
        return $this->WarmCache($data);
    }

    public function getFileLastUpdated(): string
    {
        return date('Y-m-d H:i', filemtime($this->getFilePath()));
    }


    protected function getFilePath(): string
    {
        return Controller::join_links(Director::baseFolder(), PUBLIC_DIR, $this->MyLink);
    }


    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->deleteFile();
    }

    public function deleteFile()
    {
        $path = $this->getFilePath();
        if(file_exists($path) && is_file($path)) {
            unlink($path);
        }
    }


}
