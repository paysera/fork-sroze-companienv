# Changelog

## 0.2.0

### Changed
- **BREAKING:** `Companienv\IO\UnansweredQuestionException::__construct()` requires the question. Code that throws this exception must pass it; code that catches it is unaffected.

### Fixed
- The `UnansweredQuestionException` message names the unanswered question, including in quiet mode, where `companienv -n -q` printed only a generic message. The question is no longer printed separately before the exception.

## 0.1.0

### Added
- Symfony 6 and 7.4 support for the Composer configuration script and the console command's plain-variable flow.
- PHPUnit coverage for the application, Composer script, interaction handling and configuration writing.
- Symfony 6 and 7.4 jobs in the existing GitHub Actions test matrix.

### Changed
- PHP 7.1 or later is required; the Symfony 4 components require 4.4 or later.
- Compatible Composer, PHPUnit and Symfony development dependency ranges.
- The console command accepts an empty answer when its default is empty, matching the Composer script.
- The `companion` command is registered through `Application::getDefaultCommands(): array`. Subclass overrides must declare the return type and include the parent's commands to retain it.

### Fixed
- The console command declares an integer exit status for Symfony 7 and returns a successful status after writing configuration on Symfony 5 and later.
- Non-interactive console runs accept empty defaults instead of repeatedly asking the question.
- Both interactions throw `Companienv\IO\UnansweredQuestionException` for a non-interactive question without a default. Interactive questions with no answer are asked again.
- Writing configuration passes an empty comment to dotenv-editor, avoiding PHP 8.1+ null-argument deprecations.
- Explicit nullable parameters avoid PHP 8.4 deprecations, including in the default extension classes loaded by the Composer script.

### Scope
- RSA generation, SSL certificate generation, file propagation, path resolution and value-formatting enhancements are deferred. Their existing behavior is retained; RSA and SSL generation still use the legacy Process API and are not covered by the Symfony 6/7 support claim above.
- Dependency conflict constraints exclude affected Symfony Process 5, 6 and 7.4 releases. They do not adapt the optional generation extensions to the newer Process API.
