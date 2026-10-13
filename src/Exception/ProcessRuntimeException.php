<?php

declare(strict_types=1);

namespace Archive7z\Exception;

use Symfony\Component\Process\Exception\RuntimeException as SymfonyProcessRuntimeException;

/**
 * A 7-Zip process could not be run at all (for example it failed to start).
 *
 * Extends the Symfony exception (still catchable as Symfony's) and implements
 * {@see ExceptionInterface}, so a single `catch (ExceptionInterface)` covers it
 * too. Sibling of {@see ProcessFailedException} and
 * {@see ProcessTimedOutException}.
 */
final class ProcessRuntimeException extends SymfonyProcessRuntimeException implements ExceptionInterface
{
}
