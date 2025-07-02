<?php

namespace Sunnysideup\Download\Api;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class CreateProtectedDownloadAsset
{
    use Extensible;
    use Configurable;
    use Injectable;

    private static $assets_download_folder = '_protected_downloads';

    public static function get_protected_download_files_folder(): Folder
    {
        $folderName = Config::inst()->get(static::class, 'assets_download_folder');
        $folder = Folder::find_or_make($folderName);
        self::protect_file_or_folder_and_write($folder);

        return $folder;
    }

    public static function register_download_asset_from_local_path(string $fromPath, string $fileNameToSave): File
    {
        // only works if it is in assets folder!s
        $tmpPath = Controller::join_links(ASSETS_PATH, rand() . '-' . basename($fromPath));
        if (file_exists($tmpPath)) {
            unlink($tmpPath);
        }
        rename($fromPath, $tmpPath);

        $folder = self::get_protected_download_files_folder();
        $filter = ['Name' => $fileNameToSave, 'ParentID' => $folder->ID];
        $file = File::get()->filter($filter)->first();
        if (!$file || ! $file->exists()) {
            $file = File::create();
            $file->setFromLocalFile($tmpPath, $fileNameToSave);
            $file->writeToStage(Versioned::DRAFT);
            $file->publishRecursive();
            // $finalPath = FilePathCalculator::get_path($file);
            // if(file_exists($finalPath)) {
            //     unlink($finalPath);
            // }
            // rename($tmpPath, $finalPath);
        }
        self::protect_file_or_folder_and_write($file, $filter);

        return $file;
    }

    public static function register_download_asset_from_string(string $string, string $fileNameToSave): File
    {
        $folder = self::get_protected_download_files_folder();
        $filter = ['Name' => $fileNameToSave, 'ParentID' => $folder->ID];
        $file = File::get()->filter($filter)->first();
        if (!$file || ! $file->exists()) {
            $file = File::create();
            $file->setFromString($string, $file->generateFilename());
            $file->writeToStage(Versioned::DRAFT);
            $file->publishRecursive();
        }
        self::protect_file_or_folder_and_write($file, $filter);

        return $file;
    }

    public static function get_file_from_file_name(string $name): ?File
    {
        $folder = self::get_protected_download_files_folder();
        $file = File::get()
            ->filter(['Name' => $name, 'ParentID' => $folder->ID])
            ->first();

        return $file;
    }

    protected static function protect_file_or_folder_and_write($fileOrFolder, ?array $additionalValues = [])
    {

        $fileOrFolder->CanViewType = InheritedPermissions::ONLY_THESE_USERS;
        $fileOrFolder->ShowInSearch = false;
        $fileOrFolder->ViewerGroups()->add(Permission::get_groups_by_permission('ADMIN')->first());
        foreach ($additionalValues as $key => $value) {
            $fileOrFolder->{$key} = $value;
        }
        $fileOrFolder->writeToStage(Versioned::DRAFT);
        $fileOrFolder->publishRecursive();
        return $fileOrFolder;
    }
}
