# SSL Converter

A PHP library for converting SSL certificates between different formats (PEM, PFX/PKCS12, JKS), including CA bundles and
private keys.

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

### Converting to PEM Format

```php
use SslConverter\Converter;
use SslConverter\Formats\PemFormat;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\PrivateKeyData;

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
use SslConverter\Formats\PfxFormat;

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
use SslConverter\Formats\JksFormat;

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
use SslConverter\ValueObjects\PrivateKeyData;

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
use SslConverter\Exceptions\ConversionException;

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

Developed by [Caique](https://caique.dev)

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/caiquemcz/ssl-converter/issues)
on GitHub.