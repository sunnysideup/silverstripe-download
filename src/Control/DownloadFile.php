<?php

namespace Sunnysideup\Download\Control;

use SilverStripe\Control\ContentNegotiator;
use SilverStripe\Control\Controller;
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
        $header->addHeader('Content-Type', $this->getContentType());
        $header->addHeader('Content-Disposition', 'attachment; filename=' . $this->getFilename());
        $header->addHeader('X-Robots-Tag', 'noindex');
        // return data
        return $this->getFileData();
    }

    protected function getFileData(): string
    {
        return CachedDownload::inst(
            $this->getFilename(),
            $this->getTitle(),
            $this->getMaxAgeInMinutes(),
            $this->getDeleteOnFlush(),
        )
            ->getData($this->getCallbackToCreateDownloadFile());
    }

    protected function getCallbackToCreateDownloadFile()
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


    protected function getContentType(): string
    {
        return 'text/plain';
    }

    protected function getFileName(): string
    {
        return basename($this->request->getURL(true));
    }

    protected function getTitle(): string
    {
        return 'Download';
    }


}
