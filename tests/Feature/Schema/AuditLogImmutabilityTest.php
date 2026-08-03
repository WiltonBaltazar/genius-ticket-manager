<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;

it('has no updated_at or deleted_at columns', function () {
    $columns = Schema::getColumnListing('audit_logs');

    expect($columns)->not->toContain('updated_at')
        ->and($columns)->not->toContain('deleted_at')
        ->and($columns)->toContain('created_at');
});

it('never touches updated_at on the AuditLog model', function () {
    $log = AuditLog::factory()->create();
    $originalCreatedAt = $log->created_at;

    $log->update(['action' => 'order.refunded.amended']);

    expect($log->fresh()->created_at->equalTo($originalCreatedAt))->toBeTrue();
    expect(AuditLog::UPDATED_AT)->toBeNull();
});
