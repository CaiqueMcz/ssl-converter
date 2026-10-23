# Changelog

## 3.1.0 - 2026-10-23

- Export ZIPs without a private key using only `crt` and `pem`, retaining certificate and
  CA bundle files and omitting private keys, keystores, aliases and password files.
  This path supports optional ZIP encryption and needs no external conversion tools.
  Supplied private keys remain subject to parsing, password and certificate-match checks.
- Resolve development dependencies against PHP 7.4.0 so PHPUnit uses compatible
  Doctrine Instantiator and DeepCopy versions when dependencies are installed on a newer PHP.
  This fixes mock creation errors caused by `enum_exists()` on PHP 7.4 and 8.0.
- Check real runtime platform requirements in CI after installing dependencies.

## 3.0.0 - 2026-10-23

- Add `SslConverter::toZip(string $password, bool $encryptZip = false, bool $useLegacyAlgorithm = false): string`
  to export CRT, PEM, PFX, P12 and JKS in one
  archive named `<sanitized-CN>_<12 random digits>.zip`, with `crt`, `jks`, `p12`, `pem` and
  `pfx` directly at the archive root. Return a private temporary ZIP path;
  the caller is responsible for deleting the file and its containing directory.
- Encrypt the ZIP export's PEM private key with the same password used for PFX and JKS.
  ZIP export requires a matching private key, a CA bundle, a CN, `ext-zip` and `keytool`.
- Include `crt/certificate.crt`, `crt/ca-bundle.crt` and `crt/private.key` using the PEM
  certificate, CA bundle and encrypted private key; include `jks/alias.txt` and the shared
  output password in `jks/password.txt`, `p12/password.txt` and `pfx/password.txt`.
  Without ZIP encryption, those password files are readable by anyone with archive access.
- Optionally encrypt every ZIP entry with AES-256 and the same output password using
  `toZip($password, true)`. Leave archive encryption disabled by default; fail explicitly
  when requested encryption is unsupported.
- Allow opting into legacy PFX/P12 encryption with `toZip($password, $encryptZip, true)`;
  keep native PKCS#12 generation as the default and preserve the existing first two arguments.
- Redact command lines and subprocess output from legacy OpenSSL conversion errors.
- Fix the ignored alias argument in `SslConverter::toJks()` and add `JksFormat::setAlias()`.
- Normalize every JKS alias to lowercase ASCII letters/digits and truncate to 32 characters.
  **Migration:** callers using punctuation or longer aliases must use the normalized alias
  for subsequent lookups. Aliases that become empty now raise `ConversionException`.
  This changes public alias behavior and must be considered when selecting a SemVer release.
- Protect JKS temporary files with a private directory and omit command output and passwords
  from keytool failure exceptions.
