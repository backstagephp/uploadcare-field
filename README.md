# Uploadcare Field component for the Backstage CMS.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vormkracht10/backstage-uploadcare-field.svg?style=flat-square)](https://packagist.org/packages/vormkracht10/backstage-uploadcare-field)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/vormkracht10/backstage-uploadcare-field/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/vormkracht10/backstage-uploadcare-field/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/vormkracht10/backstage-uploadcare-field/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/vormkracht10/backstage-uploadcare-field/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/vormkracht10/backstage-uploadcare-field.svg?style=flat-square)](https://packagist.org/packages/vormkracht10/backstage-uploadcare-field)

This package adds an Uploadcare field to the Backstage CMS.

## Installation

You can install the package via composer:

```bash
composer require vormkracht10/backstage-uploadcare-field
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
        \Vormkracht10\UploadcareField\UploadcareField::class,
    ],
];
```

## Usage

```php
//
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
