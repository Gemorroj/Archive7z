<?php

declare(strict_types=1);

namespace Archive7z\Exception;

use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;

/**
 * A 7-Zip process exceeded its timeout.
 *
 * Extends the Symfony exception (still catchable as Symfony's, and keeps the
 * process and the timeout type) while also implementing
 * {@see ExceptionInterface}, so a single `catch (ExceptionInterface)` covers
 * timeouts as well.
 */
final class ProcessTimedOutException extends SymfonyProcessTimedOutException implements ExceptionInterface
{
}
