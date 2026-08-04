<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's built-in VerifyEmail sends synchronously (it doesn't implement
 * ShouldQueue) — this thin subclass queues it via the database queue driver
 * so registration never blocks on mail delivery (FR-006, research.md §3).
 */
class VerifyAttendeeEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
