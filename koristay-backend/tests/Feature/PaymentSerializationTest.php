<?php

use App\Models\Booking;
use App\Models\Payment;

function paymentWithGatewayPayload(): Payment
{
    $payment = new Payment(['gateway' => 'test', 'raw_payload' => ['signature_key' => 'secret']]);
    $payment->amount = 200000;

    return $payment;
}

it('keeps the gateway payload out of serialized payments', function () {
    $payment = paymentWithGatewayPayload();

    expect($payment->toArray())->not->toHaveKey('raw_payload')
        ->and($payment->toJson())->not->toContain('secret')
        ->and($payment->raw_payload)->toBe(['signature_key' => 'secret']);
});

it('keeps the gateway payload out of payments serialized through a booking', function () {
    $booking = (new Booking)->setRelation('payments', collect([paymentWithGatewayPayload()]));

    expect($booking->toJson())->not->toContain('secret');
});
