# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Support for Symfony 6 and 7.4.
- A PHPUnit test suite.
- A GitHub Actions workflow that runs the tests on every PHP and Symfony combination it lists, with the lowest and the highest dependencies.

### Changed
- PHP 7.1 or later is required.
- `symfony/console` and `symfony/process` 4.4 or later are required on the 4.x line.
- `jackiedo/dotenv-editor` 1.1.1 or later is required.
- The development dependencies install on every PHP and Symfony combination the workflow runs.

### Fixed
- The `companienv` console command failed on Symfony 5 and later.
- RSA key and SSL certificate generation failed with `symfony/process` 5 and later.
- Declining RSA key or SSL certificate generation asked the same question again for the other variables of the pair.
- Without interaction, a question with no default (the RSA pass phrase, the SSL domain name, the path of a file to propagate) failed with a `TypeError` in the Composer script and was asked again without end in the console command. It now fails with a `RuntimeException` that names the question.
- For a variable whose default is empty, the console command asked again until a value was typed, and without interaction it asked without end. It now accepts the empty value, as the Composer script does.
- In the Composer script, an empty answer to a question with no default failed with a `TypeError`. The question is now asked again, as the console command does.
- PHP 8.1+ deprecation notices from `jackiedo/dotenv-editor` 1.2.0 and earlier when writing a variable.
- PHP 8.4 deprecations for implicitly nullable parameters.

### Security
- Key paths, the pass phrase and the domain name are no longer interpreted by a shell.
- The domain name is escaped for openssl's subject syntax, so `/`, `+` and `\` in it no longer add subject fields or make openssl fail.
- The RSA pass phrase is passed to openssl on standard input instead of the command line.

[Unreleased]: https://github.com/paysera/fork-sroze-companienv/compare/0.0.12...HEAD
