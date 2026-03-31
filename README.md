# Uploadcare Field component for the Backstage CMS.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/backstage/uploadcare-field.svg?style=flat-square)](https://packagist.org/packages/backstage/uploadcare-field)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/backstage/uploadcare-field/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/backstagephp/uploadcare-field/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/backstage/uploadcare-field/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/backstagephp/uploadcare-field/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/backstage/uploadcare-field.svg?style=flat-square)](https://packagist.org/packages/backstage/uploadcare-field)

## Nice to meet you, we're [Vormkracht10](https://vormkracht10.nl)

Hi! We are a web development agency from Nijmegen in the Netherlands and we use Laravel for everything: advanced websites with a lot of bells and whitles and large web applications.

## About this package

This package adds an Uploadcare field to the Backstage CMS. Uploadcare is a powerful file handling platform that provides file uploads, storage, transformations and delivery. With this package, you can easily integrate Uploadcare's functionality into your Backstage CMS forms.

The field supports:

-   Single and multiple file uploads
-   Image previews
-   File size limits
-   Allowed file types
-   Direct CDN delivery
-   Image transformations
-   Secure file storage

Once installed, you can use the Uploadcare field in your Backstage forms just like any other field type, while leveraging Uploadcare's robust file handling capabilities.

### Other custom fields

For a list of other custom fields, please see the [Backstage CMS documentation](https://github.com/vormkracht10/backstage/blob/main/docs/04-plugins/01-introduction.md).

## Installation

You can install the package via composer:

```bash
composer require backstage/uploadcare-field
```

Then you need to add the Uploadcare public key to your services.php config file:

```php
return [
    'uploadcare' => [
        'public_key' => env('UPLOADCARE_PUBLIC_KEY')
    ]
];
```

Then you need to add the Uploadcare field to your `backstage/fields.php` config file:

```php
return [

    // ...

    'custom_fields' => [
        Backstage\UploadcareField\Uploadcare::class,
    ],
];
```

### CSS and Styling

This package includes a MediaGridPicker component that requires Tailwind CSS classes to be properly compiled. The package automatically registers its CSS assets with Filament, but you may need to ensure your main application's Tailwind build includes the package's source files.

If you're using Tailwind CSS v4 in your main application, add the package's source directories to your `resources/css/sources.css` file:

```css
@source "/path/to/backstage-uploadcare-field/resources/";
@source "/path/to/backstage-uploadcare-field/src/";
```

For Tailwind CSS v3, add the package paths to your `tailwind.config.js`:

```javascript
module.exports = {
  content: [
    // ... your existing paths
    './vendor/backstage/uploadcare-field/resources/**/*.blade.php',
    './vendor/backstage/uploadcare-field/src/**/*.php',
  ],
  // ... rest of your config
}
```

The package's CSS is automatically loaded in Filament admin panels and includes all necessary styles for the MediaGridPicker component.

### MediaGridPicker Integration

The package includes a MediaGridPicker component that allows users to select existing media files from the media library and add them directly to Uploadcare fields. This feature is automatically available when using uploadcare fields in Filament forms.

**How it works:**
1. When editing content with uploadcare fields, a "Select from Media" button appears next to the field
2. Clicking this button opens a modal with a grid of existing media files
3. Selecting a media file automatically adds it to the Uploadcare field
4. The integration uses Alpine.js events and JavaScript to communicate between the MediaGridPicker and Uploadcare components

**Technical details:**
- The MediaGridPicker dispatches an `add-uploadcare-file` event when a file is selected
- The package's JavaScript listens for this event and attempts to add the file to the Uploadcare field
- Multiple fallback methods are used to ensure compatibility with different Uploadcare configurations:
  1. **Direct Uploadcare API**: Tries to use the Uploadcare widget's API to add files
  2. **Livewire State Management**: Updates the Livewire component state directly
  3. **Hidden Input Fields**: Sets values on hidden input fields and triggers events
  4. **File Object Creation**: Attempts to create File objects from CDN URLs
  5. **Generic Input Fields**: Falls back to setting values on any matching input fields

**Debugging:**
- The JavaScript includes comprehensive console logging to help debug integration issues
- Check the browser console for detailed information about which methods are being attempted
- The system will log which Uploadcare elements are found and which methods succeed or fail

## Automatic Migration

This package includes an automatic migration that fixes double-encoded JSON data in Uploadcare fields. This migration runs automatically when the package is installed or updated.

### What the migration does:

-   **Fixes double-encoded JSON**: Removes unnecessary JSON encoding layers that were created in earlier versions
-   **Updates both tables**: Processes both `content_field_values` and `settings` tables
-   **Safe execution**: Only runs if the relevant tables exist
-   **Logging**: Logs all changes for transparency and debugging

The migration will run automatically when you:

-   Install the package for the first time
-   Update the package via Composer
-   Run `composer update` or `composer install`

⚠️ **Important**: This migration is not reversible. Always make a database backup before updating the package.

## Usage

After adding the Uploadcare field to your `backstage/fields.php` config file, the field will automatically be available in the Backstage CMS.

### Field Configuration

The Uploadcare field supports several configuration options:

- **Multiple**: Allow multiple file uploads
- **With metadata**: Store full file metadata (recommended for cropping support)
- **Images only**: Restrict uploads to image files only
- **Uploader style**: Choose between Inline, Minimal, or Regular uploader styles

### Image Cropping and Transformations

The Uploadcare field stores comprehensive metadata about uploaded images, including cropping information. This allows you to access both the original and cropped versions of images in your front-end.

#### Understanding the Data Structure

When an image is uploaded and cropped, the field stores data in this format:

```json
{
  "uuid": "12345678-1234-1234-1234-123456789abc",
  "cdnUrl": "https://ucarecdn.com/12345678-1234-1234-1234-123456789abc/-/crop/912x442/815,0/-/preview/",
  "cdnUrlModifiers": "-/crop/912x442/815,0/-/preview/",
  "fileInfo": {
    "uuid": "12345678-1234-1234-1234-123456789abc",
    "cdnUrl": "https://ucarecdn.com/12345678-1234-1234-1234-123456789abc/",
    "imageInfo": {
      "width": 1783,
      "height": 442,
      "format": "JPEG"
    }
  }
}
```

#### Cropping Parameters Explained

The cropping is defined by URL parameters:
- `-/crop/912x442/815,0/`: 
  - `912x442` = target width and height
  - `815,0` = x,y coordinates where cropping starts
- `-/preview/` = preview mode

#### Front-end Usage

##### 1. Direct URL Access

```php
// Access the cropped version
$croppedUrl = $image['cdnUrl']; // Contains crop parameters

// Access the original version
$originalUrl = $image['fileInfo']['cdnUrl']; // No crop parameters
```

##### 2. Using Uploadcare Transformations

If you have the `backstage/php-uploadcare-transformations` package installed:

```php
// Basic resize
$resizedImage = uc($image['uuid'])->resize(width: 800);

// Smart crop with AI
$smartCropped = uc($image['uuid'])->smartCrop(width: 400, height: 300, type: 'smart_objects');

// Manual crop
$manualCrop = uc($image['uuid'])->crop(width: 400, height: 300, x: 100, y: 50);
```

##### 3. In Blade Templates

```blade
{{-- Use the pre-cropped version --}}
<img src="{{ $image['cdnUrl'] }}" alt="Cropped image" />

{{-- Use the original version --}}
<img src="{{ $image['fileInfo']['cdnUrl'] }}" alt="Original image" />

{{-- Apply new transformations --}}
<img src="{{ uc($image['uuid'])->resize(width: 600) }}" alt="Resized image" />
```

#### Best Practices

1. **Enable metadata storage**: Set `withMetadata: true` in your field configuration to ensure cropping information is preserved.

2. **Use appropriate versions**: 
   - Use `cdnUrl` for pre-cropped images
   - Use `fileInfo.cdnUrl` for original images
   - Apply new transformations as needed

3. **Performance considerations**: 
   - Pre-cropped images load faster than on-the-fly transformations
   - Use appropriate image sizes for your use case
   - Consider using WebP format for better compression

4. **Responsive images**: Use different crop sizes for different screen sizes:

```php
// Mobile
$mobileImage = uc($image['uuid'])->resize(width: 400);

// Desktop  
$desktopImage = uc($image['uuid'])->resize(width: 1200);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Baspa](https://github.com/vormkracht10)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
