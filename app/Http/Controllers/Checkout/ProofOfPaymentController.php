<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\UploadProofOfPaymentRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class ProofOfPaymentController extends Controller
{
    public function store(UploadProofOfPaymentRequest $request, Order $order): JsonResponse
    {
        abort_if($order->status !== OrderStatus::Pending, 409, 'This order can no longer accept a proof of payment.');

        $path = $request->file('file')->store('proof-of-payment', 'local');

        $order->update(['proof_of_payment_path' => $path]);

        return response()->json(['message' => 'Proof of payment uploaded.']);
    }
}
