<?php

use App\Models\Order;
use App\Models\Staff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('streams the file when it exists on disk', function () {
    $staff = Staff::factory()->eventManager()->create();
    $path = Storage::disk('local')->putFile('proof-of-payment', UploadedFile::fake()->image('receipt.jpg'));
    $order = Order::factory()->pending()->create(['proof_of_payment_path' => $path]);

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}/proof-of-payment-file");

    $response->assertOk();
});

it('returns 404, not a 500, when the DB path points at a file no longer on disk', function () {
    // Regression: Storage::response() calls straight through to Flysystem's
    // fileSize(), which throws UnableToRetrieveMetadata — an uncaught 500 —
    // when the file is missing, rather than the 404 a missing path already
    // gets. Seen in production for a real order whose file had gone missing.
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create(['proof_of_payment_path' => 'proof-of-payment/does-not-exist.jpg']);

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}/proof-of-payment-file");

    $response->assertNotFound();
});

it('returns 404 when the order has no proof-of-payment path at all', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create(['proof_of_payment_path' => null]);

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}/proof-of-payment-file");

    $response->assertNotFound();
});

it('refuses staff without orders access', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $path = Storage::disk('local')->putFile('proof-of-payment', UploadedFile::fake()->image('receipt.jpg'));
    $order = Order::factory()->pending()->create(['proof_of_payment_path' => $path]);

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}/proof-of-payment-file");

    $response->assertForbidden();
});
