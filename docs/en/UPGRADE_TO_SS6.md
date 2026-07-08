# Upgrade Guide: Moving to Silverstripe CMS 6

This document outlines the necessary changes to upgrade your project to be compatible with this module's Silverstripe 6 version.

## Core Dependency Updates

⚠️ **BREAKING CHANGE**: The framework requirements have been updated. Your project must now use Silverstripe CMS v6.

-   `silverstripe/framework` is now required at `^6.0` (previously `^5.0`).
-   `silverstripe/admin` is now required at `^3.0` (previously `^2.0`).

Ensure your project's `composer.json` reflects these new constraints.

## API Changes & Deprecations

### Model: `Sunnysideup\Download\Model\CachedDownload`

A number of methods now use the native PHP `Override` attribute to indicate they are overriding parent methods. This is a non-breaking change but improves code clarity.

-   `getCMSFields()`
-   `onBeforeWrite()`
-   `onBeforeDelete()`
-   `canEdit()`
-   `canCreate()`

### Flush Logic

The logic within the `flush()` method on `CachedDownload` has been updated to align with changes in the Silverstripe Framework.

-   The check `Controller::has_curr() === false` has been replaced with `!Controller::curr() instanceof Controller`. This ensures compatibility with the latest controller-awareness checks in Silverstripe 6.
-   The dependency on `SilverStripe\Dev\DevBuildController` has been removed from the `use` statements, as it was only used in the flush check.