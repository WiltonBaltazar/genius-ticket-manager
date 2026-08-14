<?php

namespace App\Actions\Checkout;

use RuntimeException;

/**
 * Thrown inside SubmitOrderAction's DB::transaction() closure to trigger an
 * automatic rollback (Laravel's transaction() catches Throwable and rolls
 * back before re-throwing) — never rendered directly, always caught by
 * SubmitOrderAction itself.
 */
class InsufficientAvailabilityException extends RuntimeException
{
    /**
     * @param  array<string, int>  $shortfalls
     */
    public function __construct(public readonly array $shortfalls)
    {
        parent::__construct('One or more ticket types no longer have sufficient availability.');
    }
}
