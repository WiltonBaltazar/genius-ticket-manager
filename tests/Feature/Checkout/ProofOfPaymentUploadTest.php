<?php

use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
});

function uploadProofOfPayment(Order $order, array $data): TestResponse
{
    // Plain post() (not postJson()) since a real file upload must be
    // multipart/form-data, but Accept: application/json is what a real
    // fetch()-based client sends (see lib/auth.ts's postFormData) and is
    // what makes Laravel return a JSON error response instead of a
    // redirect-back-with-flashed-errors on validation failure.
    return test()->post("/orders/{$order->id}/proof-of-payment", $data, ['Accept' => 'application/json']);
}

it('accepts a proof-of-payment upload while the order is pending', function () {
    $order = Order::factory()->pending()->create();

    $response = uploadProofOfPayment($order, ['file' => UploadedFile::fake()->image('receipt.jpg')]);

    $response->assertOk();
    expect($order->fresh()->proof_of_payment_path)->not->toBeNull();
});

it('rejects an upload once the order is paid', function () {
    $order = Order::factory()->create(); // Paid by default

    $response = uploadProofOfPayment($order, ['file' => UploadedFile::fake()->image('receipt.jpg')]);

    $response->assertStatus(409);
});

it('rejects an upload once the order is expired', function () {
    $order = Order::factory()->expired()->create();

    $response = uploadProofOfPayment($order, ['file' => UploadedFile::fake()->image('receipt.jpg')]);

    $response->assertStatus(409);
});

it('rejects a missing or invalid file', function () {
    $order = Order::factory()->pending()->create();

    uploadProofOfPayment($order, [])->assertUnprocessable();

    uploadProofOfPayment($order, [
        'file' => UploadedFile::fake()->create('not-allowed.exe', 10),
    ])->assertUnprocessable();
});

it('accepts a file up to 40MB and rejects one over that', function () {
    $order = Order::factory()->pending()->create();

    uploadProofOfPayment($order, [
        'file' => UploadedFile::fake()->create('receipt.jpg', 40960 - 1),
    ])->assertOk();

    $order2 = Order::factory()->pending()->create();

    uploadProofOfPayment($order2, [
        'file' => UploadedFile::fake()->create('receipt.jpg', 40960 + 1),
    ])->assertUnprocessable();
});
