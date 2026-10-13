<?php

declare(strict_types=1);

namespace Archive7z\Exception;

use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailedException;

/**
 * A fatal failure of the 7-Zip process (exit code >= 2, see ExitCode.h).
 *
 * Extends the Symfony exception, so it still carries the whole process (exit
 * code, output, error output) and stays catchable as Symfony's type, while
 * also implementing {@see ExceptionInterface} so the library has a single
 * catchable contract.
 */
final class ProcessFailedException extends SymfonyProcessFailedException implements ExceptionInterface
{
}
