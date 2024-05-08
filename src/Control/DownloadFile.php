<?php

namespace Sunnysideup\Download\Control;

use SilverStripe\Control\ContentNegotiator;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\View\SSViewer;
use Sunnysideup\Download\Control\Model\CachedDownload;

abstract class DownloadFile extends Controller
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index' => true,
    ];


    /**
     * returns the file.
     * @return mixed
     */
    public function index()
    {
        Config::modify()->set(SSViewer::class, 'set_source_file_comments', false);
        Config::modify()->set(ContentNegotiator::class, 'enabled', false);
        // response header
        $header = $this->getResponse();
        $header->addHeader('Pragma', 'no-cache');
        $header->addHeader('Expires', 0);
        $header->addHeader('X-Robots-Tag', 'noindex');
        $header->addHeader('cache-control', 'no-cache, no-store, must-revalidate');
        HTTPCacheControlMiddleware::singleton()->disableCache();
        // return data
        $data = $this->getFileData();
        $fileName = $this->getFileName();
        $contentType = $this->getContentType();
        return HTTPRequest::send_file(
            $data,
            $fileName,
            $contentType,
        );
    }

    /**
     * gets the file data from cache or live
     *
     * @return string
     */
    protected function getFileData(): string
    {
        $obj = $this->findOrCreateCachedDownload();
        return $obj->getData($this->getCallbackToCreateDownloadFile(), $this->getFileName());
    }

    protected function findOrCreateCachedDownload(): CachedDownload
    {
        $obj = CachedDownload::inst(
            $this->getFileUrl(),
            $this->getTitle(),
        );
        $obj->DeleteOnFlush = $this->getDeleteOnFlush();
        $obj->MaxAgeInMinutes = $this->getMaxAgeInMinutes();
        $obj->HasControlledAccess = $this->getHasControlledAccess();
        $obj->write();
        return $obj;
    }

    /**
     * function (closure) that runs when there is nothing saved on file.
     *
     * @return callable
     */
    protected function getCallbackToCreateDownloadFile(): callable
    {
        return function () {
            return $this->renderWith(static::class);
        };
    }

    protected function getMaxAgeInMinutes(): ?int
    {
        return null; // set to null to use default
    }

    protected function getDeleteOnFlush(): ?bool
    {
        return null; // set to null to use default
    }

    protected function getHasControlledAccess(): ?bool
    {
        return null; // set to null to use default
    }


    protected function getContentType(): string
    {
        return 'text/plain';
    }

    protected function getFileName(): string
    {
        return basename($this->getFileUrl());
    }

    protected function getFileUrl(): string
    {
        return $this->getRequest()->getURL(false);
    }

    protected function getTitle(): string
    {
        return 'Download';
    }
}
