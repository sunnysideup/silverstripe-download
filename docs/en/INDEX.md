# How to setup:

### 1. extend `DownloadFile` to add extra functionality

```php
namespace Website\App\Control;
use Sunnysideup\Download\DownloadFile;

class MyDownloadFile extends DownloadFile
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index' => false,
    ];

    // more fx goes here...

    protected function getContentType(): string
    {
        return 'text/csv';
    }

    protected function getFileName(): string
    {
        return 'mydownload.' . $this->getExtension();
    }

    protected function getExtension(): string
    {
        return '.csv';
    }
}

```

### 2. add a route to your file

```yml
---
Name: app_downloads_routes
---
SilverStripe\Control\Director:
  rules:
    mydownload.csv: Website\App\Control\MyDownloadFile
```

### 3. add a template in `themes/mytheme/templates/Website/App/Control/MyDownloadFile.ss`

```html
CSV content goes here...
```
