# AGENTS.md

PHP >= 8.2 library. 7z CLI wrapper (`Archive7z\Archive7z`) based on `symfony/process`.

## Commands
- Install: `composer install`
- Test: `vendor/bin/phpunit` (excludes known issues)
- Known issues (expected failures): `vendor/bin/phpunit --testsuite KnownIssues`
- Static analysis: `vendor/bin/phpstan analyse`
- Code style fix: `PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix`

## Rules
- Follow PSR-12, strict types (`declare(strict_types=1)`).
- Prefer composition over inheritance; `Archive7z` is `final`.
- Don't use arrow functions (`fn`).
- Public API changes are allowed in major releases; keep them deliberate.
- Requires `proc_open` and 7z binary.
- Run tests and static analysis before committing.
- Never commit or push; the human does it.
