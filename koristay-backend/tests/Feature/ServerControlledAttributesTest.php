<?php

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;

dataset('server controlled attributes', [
    'booking total_price' => [Booking::class, 'total_price', 1],
    'booking status' => [Booking::class, 'status', 'confirmed'],
    'payment amount' => [Payment::class, 'amount', 1],
    'payment status' => [Payment::class, 'status', 'paid'],
]);

it('rejects server controlled attributes through mass assignment in strict mode', function (string $model, string $attribute, mixed $value) {
    (new $model)->fill([$attribute => $value]);
})->with('server controlled attributes')->throws(MassAssignmentException::class);

it('discards server controlled attributes through mass assignment outside strict mode', function (string $model, string $attribute, mixed $value) {
    $strict = Model::preventsSilentlyDiscardingAttributes();
    Model::preventSilentlyDiscardingAttributes(false);

    try {
        $instance = (new $model)->fill([$attribute => $value]);

        expect($instance->getAttributes())->not->toHaveKey($attribute);
    } finally {
        Model::preventSilentlyDiscardingAttributes($strict);
    }
})->with('server controlled attributes');
