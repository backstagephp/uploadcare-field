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

Then you need to add the Uploadcare field to your `backstage.php` config file:

```php
return [
    'fields' => [
        Backstage\UploadcareField\Uploadcare::class,
    ],
];
```

## Usage

After adding the Uploadcare field to your `backstage.php` config file, the field will automatically be available in the Backstage CMS.

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
