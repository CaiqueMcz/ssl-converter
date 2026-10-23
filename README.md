# SSL Converter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

A PHP library for converting SSL certificates between different formats (PEM, PFX/PKCS12, JKS), including CA bundles and private keys.

## Features

- Convert certificates to PEM format with full chain
- Convert certificates to PFX/PKCS12 format
- Convert certificates to JKS (Java KeyStore) format
- Export CRT, PEM, PFX, P12 and JKS in a temporary ZIP named after the certificate common name
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
- ZIP extension (`ext-zip`, for ZIP export only)

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

### Export All Formats as a ZIP

```php
use CaiqueMcz\SslConverter\SslConverter;

$converter = (new SslConverter($certificate))
    ->withCaBundle($caBundle)
    ->withPrivateKey($privateKey, $inputKeyPassword); // optional input key password

$zipPath = $converter->toZip('SharedPassword123!');

// To also encrypt every ZIP entry with that same password:
// $zipPath = $converter->toZip('SharedPassword123!', true);

// To generate legacy PFX/P12 files (the second argument still controls ZIP encryption):
// $zipPath = $converter->toZip('SharedPassword123!', false, true);
// $zipPath = $converter->toZip('SharedPassword123!', true, true); // also encrypt the ZIP

try {
    // Send or copy the archive using $zipPath.
} finally {
    unlink($zipPath);
    rmdir(dirname($zipPath));
}
```

For a certificate with CN `example.com`, the filename is `example.com_<12 random digits>.zip`.
The format directories sit directly at the root of the archive:

```text
crt/
  ca-bundle.crt
  certificate.crt
  private.key
jks/
  alias.txt
  certificate.jks
  password.txt
p12/
  certificate.p12
  password.txt
pem/
  ca-bundle.pem
  certificate.pem
  fullchain.pem
  private.pem
pfx/
  certificate.pfx
  password.txt
```

`toZip(string $password, bool $encryptZip = false, bool $useLegacyAlgorithm = false): string`
requires a parseable certificate with a single nonempty CN,
a matching private key, a CA bundle, `ext-zip`, and Java `keytool`. It returns an absolute
path such as `/tmp/ssl_converter_<random>/example.com_123456789012.zip`, under `sys_get_temp_dir()`.
Each filename uses a fresh cryptographically random 12-digit suffix; repeated calls create
separate archives. The caller must delete the ZIP and its containing temporary directory
when finished. On POSIX systems, the directory has mode `0700` and the
completed ZIP has mode `0600`. Conversion failures remove partially generated files.

PFX and P12 contain the same encrypted PKCS#12 data under their respective filenames.
The `crt` directory reuses the PEM-encoded certificate and CA bundle with `.crt` filenames;
`private.key` contains the same encrypted PEM private key as `pem/private.pem`.
The JKS directory includes `alias.txt` with the normalized alias actually used by the keystore.
Each JKS, P12 and PFX directory includes `password.txt` containing the shared output password.
These text files contain the exact values, without an appended newline.

The same password protects PFX/P12, the JKS keystore and key entry, and the encrypted PEM private
key (AES-256-CBC). It must have at least six characters, as required by `keytool`. An encrypted
input key can have a different password, supplied through `withPrivateKey()`; the exported
PEM key is encrypted with the shared output password. By default, the ZIP container has no password.
In that mode, the included `password.txt` files expose the password needed to open the private keys.
Pass `true` as the second argument to encrypt every entry with AES-256 using the shared password.
This requires `ext-zip` built with libzip >= 1.2.0 and an extractor that supports AES-encrypted ZIPs;
if encryption is unavailable or fails, export fails without returning an unprotected archive.
Entry filenames remain visible even when encryption is enabled. See the
[PHP ZIP encryption documentation](https://www.php.net/manual/en/ziparchive.setencryptionname.php).
No plaintext private key is included in the ZIP.

The third argument, `$useLegacyAlgorithm`, controls legacy generation of both PFX and P12.
It defaults to `false`. Set it to `true` only for systems requiring older PKCS#12 encryption
algorithms. This option uses the existing OpenSSL CLI `pkcs12 -export -legacy` conversion
and requires OpenSSL 3 with its legacy provider available. These older algorithms provide
weaker protection; see the [OpenSSL PKCS#12 documentation](https://docs.openssl.org/3.0/man1/openssl-pkcs12/).
ZIP encryption is configured separately with the second argument. Legacy conversion stages
an unencrypted PEM private key in a random temporary file with owner-only permissions on
POSIX systems, then removes the staging files on success or failure.

The ZIP filename prefix preserves the CN's ASCII letters, digits, dots, underscores and hyphens;
other characters (including a wildcard `*`) become underscores. Leading/trailing dots are
removed, and Windows device names are prefixed with an underscore. For example,
`*.example.com` becomes `_.example.com_<12 random digits>.zip`. There is no enclosing CN directory
inside the ZIP. The JKS alias is derived from the original CN
using the alias policy below. PEM retains CA bundle order; PKCS#12 tools can reorder extra
certificates, and `keytool` constructs the JKS certificate chain from linked certificates.

These rules apply to ZIP export. Existing individual format methods retain their password
and PEM key handling behavior.

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
- ZIP export also includes the same PKCS#12 container as `p12/certificate.p12`
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

JKS aliases, including custom aliases passed to `toJks()`, are normalized to lowercase ASCII
letters and digits (`a-z`, `0-9`), with other characters removed, and truncated to 32 characters.
For example, `*.Example.COM` becomes `examplecom`. An alias with no remaining letters or
digits raises `ConversionException`. This is the library's interoperability policy, rather
than a Java-wide 32-character restriction. Use the normalized alias when accessing the entry
with `keytool`. Direct `JksFormat` callers can configure it with `setAlias()`.

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
