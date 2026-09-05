# AGENTS.md

PHP >= 8.2 library. 7z CLI wrapper (`Archive7z\Archive7z`) based on `symfony/process`.

## Structure
- `src/` — library code (PSR-4 `Archive7z\`)
- `tests/` — PHPUnit tests, `tests/fixtures/` — test archives

## Commands
- Install: `composer install`
- Test: `vendor/bin/phpunit`
- Static analysis: `vendor/bin/phpstan analyse`
- Code style fix: `PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix`

## Rules
- Follow PSR-12, strict types (`declare(strict_types=1)`).
- Keep backward compatibility of public API (`Archive7z`, `Entry`, `Info`).
- Requires `proc_open` and 7z binary.
- Run tests and static analysis before committing.
