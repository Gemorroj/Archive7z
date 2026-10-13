<?php

declare(strict_types=1);

namespace Archive7z\Exception;

/**
 * Marker for every exception the library throws on its own behalf.
 *
 * Covers argument/configuration errors, runtime preconditions and 7-Zip
 * process failures ({@see ProcessFailedException}), so a single
 * `catch (ExceptionInterface $e)` catches them all.
 */
interface ExceptionInterface extends \Throwable
{
}
