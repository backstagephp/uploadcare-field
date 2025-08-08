# Changelog

All notable changes to `backstage-uploadcare-field` will be documented in this file.

## v0.5.0 - 2025-01-27

### What's Changed

-   **BREAKING**: Added automatic migration to fix double-encoded JSON data in Uploadcare fields
-   The migration runs automatically when the package is installed or updated
-   Fixes data compatibility issues with Uploadcare version 0.3.8 and above
-   Processes both `content_field_values` and `settings` tables
-   Includes comprehensive logging for transparency

⚠️ **Important**: This migration is not reversible. Always make a database backup before updating.

## v0.4.0 - 2025-06-23

### What's Changed

-   Bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot in https://github.com/backstagephp/uploadcare-field/pull/3
-   feat: improve handling proxy states and builders by @Baspa in https://github.com/backstagephp/uploadcare-field/pull/5

### New Contributors

-   @Baspa made their first contribution in https://github.com/backstagephp/uploadcare-field/pull/5

**Full Changelog**: https://github.com/backstagephp/uploadcare-field/compare/v0.3.0...v0.4.0

## v0.3.0 - 2025-04-13

**Full Changelog**: https://github.com/backstagephp/uploadcare-field/compare/v0.3.0...v0.3.0

## v0.2.0 - 2025-04-13

**Full Changelog**: https://github.com/backstagephp/uploadcare-field/compare/v0.1.0...v0.2.0

## v0.1.0 - 2025-01-17

**Full Changelog**: https://github.com/backstagephp/uploadcare-field/commits/v0.1.0
