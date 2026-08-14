<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a pending order's uploaded proof-of-payment file to staff
 * (004-attendee-checkout, FR-020) — the file lives on the private `local`
 * disk (storage/app/private), never a publicly-listable one, so this
 * auth-gated route is the only way to reach it.
 */
class OrderProofOfPaymentController extends Controller
{
    public function show(Order $order): StreamedResponse
    {
        abort_if(! $order->proof_of_payment_path, 404);
        abort_unless(auth('staff')->user()?->can('view', $order), 403);

        return Storage::disk('local')->response($order->proof_of_payment_path);
    }
}
