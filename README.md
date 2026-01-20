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
