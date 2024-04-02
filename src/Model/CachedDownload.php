<?php

namespace Sunnysideup\Download\Control\Model;

use Closure;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Flushable;
use SilverStripe\Dev\DevBuildController;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Security\Group;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\Download\Api\FilePathCalculator;
use Sunnysideup\Download\Api\CreateProtectedDownloadAsset;

/**
 *
 * @property string $Title
 * @property string $Link
 * @property int $ProductCount
 */
class CachedDownload extends DataObject implements Flushable
{
    protected static $assets_download_folder = '__protected_downloads';

    public static function file_path(string $fileName): string
    {
        return Controller::join_links(PUBLIC_PATH, $fileName);
    }

    public static function flush()
    {
        if(Security::database_is_ready() && DB::get_schema()->hasTable('CachedDownload')) {
            if(Controller::has_curr() === false || get_class(Controller::curr()) === DevBuildController::class) {
                return;
            }
            $list = self::get();
            foreach ($list as $item) {
                if($item->DeleteOnFlush) {
                    $item->delete();
                }
            }
        }
    }


    public static function inst(string $myLink, ?string $title = ''): self
    {
        $obj = self::get()->filter(['MyLink' => $myLink])->first();
        if (!$obj) {
            $obj = self::create();
        }
        $obj->MyLink = $myLink;
        $obj->Title = $title ?: $myLink;
        $obj->write();

        return $obj;
    }

    private static $max_age_in_minutes = 60;

    private static $table_name = 'CachedDownload';

    private static $db = [
        'Title' => 'Varchar(255)',
        'MyLink' => 'Varchar(255)',
        'DeleteOnFlush' => 'Boolean(1)',
        'MaxAgeInMinutes' => 'Int',
        'HasControlledAccess' => 'Boolean',
    ];

    private static $has_one = [
        'ControlledAccessFile' => File::class,
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $indexes = [
        'MyLink' => true,
    ];

    private static $cascade_deletes = [
        'ControlledAccessFile',
    ];


    //######################
    //## Names Section
    //######################

    private static $singular_name = 'Cached Download';

    private static $plural_name = 'Cached Downloads';

    private static $summary_fields = [
        'Title' => 'Name',
        'MyLink' => 'Link',
        'HasControlledAccess.Nice' => 'Controlled Access',
    ];

    private static $casting = [
        'IsExpired.Nice' => 'Boolean',
    ];


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create(
                    'LastEditedNice',
                    'When was cache created?',
                ),
                ReadonlyField::create(
                    'AgeOfFile',
                    'When was the file written?',
                    $this->getFileLastUpdated()
                ),
                ReadonlyField::create(
                    'SizeOfFile',
                    'What is the size of the file?',
                    $this->getSizeOfFile()
                ),
                LiteralField::create(
                    'ReviewCurrentOne',
                    '<p class="message good"><a href="' . $this->MyLink . '">Review current version</a></p>',
                ),
                LiteralField::create(
                    'CreateNewOne',
                    '<p class="message warning">Create a new cache by deleting this cache.</p>',
                ),

            ]
        );
        if(! $this->HasControlledAccess) {
            $fields->removeByName('ControlledAccessFileID');
        }

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->IsExperired()) {
            $this->deleteFile();
        }
        if($this->IsExperiredFile()) {
            $this->deleteFile();
        }
    }

    public function getLastEditedNice()
    {
        return DBField::create_field(DBDatetime::class, $this->LastEdited)->ago();
    }


    /**
     * Returns file details.
     * If the cache is expired, it redoes (warms) the cache.
     * Otherwise straight from the cached file
     *
     * @return string
     */
    public function getData(callable $callBackIfEmpty, string $fileNameToSave): string
    {
        // check for latest data!
        $this->write();
        $path = $this->getFilePath(true);
        if ($path && file_exists($path)) {
            if($this->ControlledAccessFile()->Name !== $fileNameToSave) {
                $this->deleteFile();
            } else {
                return file_get_contents($path);
            }
        }
        $data = $callBackIfEmpty();
        if($data) {
            return $this->WarmCache($data, $fileNameToSave);
        } else {
            user_error('Could not create download file');
            return 'Could not create download file';
        }
    }

    public function WarmCache(string $data, ?string $fileNameToSave = ''): string
    {
        if($this->HasControlledAccess) {
            // file should have already been created during callback!
            $file = CreateProtectedDownloadAsset::get_file_from_file_name($fileNameToSave);
            if($file && $file->exists()) {
                // all done
            } else {
                $file = CreateProtectedDownloadAsset::register_download_asset_from_string($data, $fileNameToSave);
            }
            if($file && $file->exists()) {
                $this->ControlledAccessFileID = $file->ID;
                $this->write();
            }
            return $data;
        } else {
            $filePath = $this->getFilePath();
            if($filePath) {
                if($this->createDirRecursively(dirname($filePath))) {
                    file_put_contents($filePath, $data);
                    return file_get_contents($filePath);
                }
            } else {
                user_error('file path is empty');
                return $data;
            }
        }
        return $data;
    }


    public function IsExperired(): bool
    {
        $maxAgeInSeconds = ($this->MaxAgeInMinutes ?: $this->Config()->max_age_in_minutes) * 60;
        $maxCacheAge = strtotime('now') - $maxAgeInSeconds;
        return $this->LastEdited && strtotime((string) $this->LastEdited) < $maxCacheAge;
    }


    public function IsExperiredFile(?string $path = ''): bool
    {
        if(! $path) {
            $path = $this->getFilePath();
        }
        if(file_exists($path) && is_file($path)) {
            $maxAgeInSeconds = ($this->MaxAgeInMinutes ?: $this->Config()->max_age_in_minutes) * 60;
            $maxCacheAge = strtotime('now') - $maxAgeInSeconds;
            $timeChange = filemtime($path);
            return $timeChange < $maxCacheAge;
        }
        return false;
    }

    public function getAbsoluteLink(): string
    {
        return Controller::join_links(Director::absoluteBaseURL(), $this->MyLink);
    }


    public function getFileLastUpdated(): string
    {
        $path = $this->getFilePath();
        if($path) {
            return date('Y-m-d H:i', filemtime($path));
        }
        return 'no date';
    }

    public function getSizeOfFile(): string
    {
        $path = $this->getFilePath();
        if($path) {
            return $this->formatFileSize(filesize($path));
        }
        return 'empty';
    }



    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->deleteFile();
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function deleteFile()
    {
        $path = $this->getFilePath();
        if($path && file_exists($path) && is_file($path)) {
            unlink($path);
        }
        $file = $this->ControlledAccessFile();
        if($file && $file->exists()) {
            $file->doArchive();
        }
        $this->ControlledAccessFileID = 0;
        $this->write();
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor(log($bytes, 1024));

        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor - 1]);
    }

    protected function createDirRecursively(string $path, int $permissions = 0755): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, $permissions, true);
        }
        return true;
    }

    protected function getFilePath(?bool $alsoCheckForCanView = false): string
    {
        $path = '';
        if($this->HasControlledAccess) {
            if($this->ControlledAccessFileID) {
                $file = $this->ControlledAccessFile();
                if($file && $file->exists()) {
                    if($alsoCheckForCanView === false || $file->canView()) {
                        $path = FilePathCalculator::get_path($file);
                    }
                }
            }
        } else {
            $path = self::file_path($this->MyLink);
        }
        return $path;
    }

}
