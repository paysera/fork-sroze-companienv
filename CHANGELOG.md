# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-30

### Added
- Support for Symfony 6 and 7.
- A PHPUnit test suite.
- A GitHub Actions workflow that runs the tests on every supported PHP and Symfony version.

### Changed
- PHP 7.1 or later is required.
- `symfony/console` and `symfony/process` 3.4 or later are required.
- The development dependencies install on every supported PHP and Symfony version.

### Fixed
- The `companienv` console command failed on Symfony 5 and later.
- RSA key and SSL certificate generation failed with `symfony/process` 5 and later.
- Declining RSA key or SSL certificate generation asked the same question again for the other variables of the pair.
- PHP 8.1+ deprecation notices from `jackiedo/dotenv-editor` 1.0 and 1.1 when writing a variable.
- PHP 8.4 deprecations for implicitly nullable parameters.

[0.1.0]: https://github.com/paysera/fork-sroze-companienv/compare/0.0.12...0.1.0
