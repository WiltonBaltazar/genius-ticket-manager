<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's built-in ResetPassword sends synchronously (it doesn't implement
 * ShouldQueue) — this thin subclass queues it via the database queue driver,
 * matching VerifyAttendeeEmail's rationale (FR-006/FR-018, research.md §3).
 */
class ResetAttendeePassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
