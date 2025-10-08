# SSL Converter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

A PHP library for converting SSL certificates between different formats (PEM, PFX/PKCS12, JKS), including CA bundles and private keys.

## Features

- Convert certificates to PEM format with full chain
- Convert certificates to PFX/PKCS12 format
- Convert certificates to JKS (Java KeyStore) format
- Support for encrypted private keys
- CA bundle handling
- Legacy algorithm support for older systems
- Clean, SOLID architecture
- Fully tested with PHPUnit

## Requirements

- PHP >= 7.4
- OpenSSL extension
- Symfony Process component
- Java keytool (for JKS conversion)

## Installation

```bash
composer require caiquemcz/ssl-converter
```

## Usage

### Quick Start with Fluent API (Recommended)

The simplest way to convert certificates is using the fluent `SslConverter` API:

```php
use CaiqueMcz\SslConverter\SslConverter;

// Convert to PEM
$response = (new SslConverter($certificate))
    ->withCaBundle($caBundle)
    ->withPrivateKey($privateKey, $password)  // password is optional
    ->toPem();

// Convert to PFX
$response = (new SslConverter($certificate))
    ->withCaBundle($caBundle)              // optional
    ->withPrivateKey($privateKey, $password)  // password is optional
    ->toPfx('pfx-password', $useLegacy);   // useLegacy is optional (default: false)

// Convert to JKS
$response = (new SslConverter($certificate))
    ->withCaBundle($caBundle)              // optional
    ->withPrivateKey($privateKey, $password)  // password is optional
    ->toJks('jks-password', 'alias', $useLegacy);  // alias and useLegacy are optional

// Save the converted file
file_put_contents(
    $response->virtualFile()->getName(),
    $response->virtualFile()->getContent()
);
```

### Real-World Examples

**Example 1: Convert PEM to PFX for Windows servers**
```php
use CaiqueMcz\SslConverter\SslConverter;

$certificate = file_get_contents('certificate.pem');
$privateKey = file_get_contents('private.key');
$caBundle = file_get_contents('ca-bundle.pem');

$response = (new SslConverter($certificate))
    ->withCaBundle($caBundle)
    ->withPrivateKey($privateKey)
    ->toPfx('SecurePassword123!');

file_put_contents('certificate.pfx', $response->virtualFile()->getContent());
```

**Example 2: Convert to JKS for Java applications**
```php
use CaiqueMcz\SslConverter\SslConverter;

$certificate = file_get_contents('certificate.crt');
$privateKey = file_get_contents('private.key');

$response = (new SslConverter($certificate))
    ->withPrivateKey($privateKey)
    ->toJks('keystore-password', 'myapp');

file_put_contents('keystore.jks', $response->virtualFile()->getContent());
```

**Example 3: Handle encrypted private keys**
```php
use CaiqueMcz\SslConverter\SslConverter;

$certificate = file_get_contents('certificate.pem');
$encryptedKey = file_get_contents('encrypted-private.key');

$response = (new SslConverter($certificate))
    ->withPrivateKey($encryptedKey, 'key-encryption-password')
    ->toPfx('pfx-password');

file_put_contents('certificate.pfx', $response->virtualFile()->getContent());
```

**Example 4: Use legacy algorithm for older systems**
```php
use CaiqueMcz\SslConverter\SslConverter;

$certificate = file_get_contents('certificate.pem');
$privateKey = file_get_contents('private.key');

$response = (new SslConverter($certificate))
    ->withPrivateKey($privateKey)
    ->toPfx('password', true);  // true = use legacy algorithm

file_put_contents('certificate.pfx', $response->virtualFile()->getContent());
```

### Advanced Usage with Converters

For more control, use the converter classes directly:

### Converting to PEM Format

```php
use CaiqueMcz\SslConverter\Converter;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

$certificateData = new CertificateData(
    $certificate,
    new PrivateKeyData($privateKey, $privateKeyPassword),
    $caBundle
);

$pemFormat = new PemFormat($certificateData);
$converter = new Converter();

$response = $converter->convert($pemFormat);

// Get the main file (fullchain.pem)
$mainFile = $response->virtualFile();
echo $mainFile->getName();    // fullchain.pem
echo $mainFile->getContent(); // Certificate content

// Get extra files (ca-bundle.pem, private.pem)
foreach ($response->extraVirtualFile() as $file) {
    echo $file->getName();
    echo $file->getContent();
}
```

### Converting to PFX Format

```php
use CaiqueMcz\SslConverter\Formats\PfxFormat;

$certificateData = new CertificateData(
    $certificate,
    new PrivateKeyData($privateKey),
    $caBundle
);

$pfxFormat = new PfxFormat($certificateData);
$pfxFormat->setPfxPassword('your-password');

// Optional: Use legacy algorithm for older systems
$pfxFormat->withLegacyAlgorithm();

$converter = new Converter();
$response = $converter->convert($pfxFormat);

$pfxFile = $response->virtualFile();
file_put_contents('certificate.pfx', $pfxFile->getContent());
```

### Converting to JKS Format

```php
use CaiqueMcz\SslConverter\Formats\JksFormat;

$certificateData = new CertificateData(
    $certificate,
    new PrivateKeyData($privateKey),
    $caBundle
);

$jksFormat = new JksFormat($certificateData);
$jksFormat->setJksPassword('your-password');

// Optional: Use legacy algorithm
$jksFormat->withLegacyAlgorithm();

$converter = new Converter();
$response = $converter->convert($jksFormat);

$jksFile = $response->virtualFile();
file_put_contents('certificate.jks', $jksFile->getContent());
```

### Working with Encrypted Private Keys

```php
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

// Private key with password
$privateKeyData = new PrivateKeyData($privateKey, 'key-password');

$certificateData = new CertificateData(
    $certificate,
    $privateKeyData,
    $caBundle
);

// Use as normal
$pfxFormat = new PfxFormat($certificateData);
$pfxFormat->setPfxPassword('pfx-password');
```

### Getting All Files

```php
$response = $converter->convert($format);

// Get all files including main and extras
$allFiles = $response->getAllVirtualFiles();

foreach ($allFiles->get() as $file) {
    echo sprintf(
        "File: %s (Size: %d bytes)\n",
        $file->getName(),
        $file->getSize()
    );
    
    file_put_contents($file->getName(), $file->getContent());
}
```

## Format Support

### PEM Format
- Generates `fullchain.pem` (certificate + CA bundle)
- Optional `ca-bundle.pem` (CA certificates)
- Optional `private.pem` (private key)
- Requires CA bundle

### PFX/PKCS12 Format
- Generates `certificate.pfx`
- Requires private key
- Requires password
- Supports legacy algorithm for compatibility
- Optional CA bundle

### JKS Format
- Generates `certificate.jks`
- Requires private key
- Requires password
- Requires Java keytool installed
- Supports legacy algorithm
- Optional CA bundle
- Custom alias support

## Architecture

The library follows SOLID principles with a clean architecture:

- **Value Objects**: Immutable data containers (`CertificateData`, `PrivateKeyData`, `VirtualFile`)
- **Formats**: Strategy pattern for different certificate formats
- **Generators**: Factory pattern for complex file generation
- **Converters**: Facade for simple API
- **Collections**: Type-safe collections for virtual files

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Code style check
composer lint

# Fix code style
composer lint-fix
```

## Error Handling

All conversion errors throw `ConversionException`:

```php
use CaiqueMcz\SslConverter\Exceptions\ConversionException;

try {
    $response = $converter->convert($format);
} catch (ConversionException $e) {
    echo "Conversion failed: " . $e->getMessage();
}
```

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

Developed by [Caique](https://github.com/caiquemcz)

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/caiquemcz/ssl-converter/issues) on GitHub.