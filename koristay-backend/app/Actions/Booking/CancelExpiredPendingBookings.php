<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelExpiredPendingBookings
{
    use AsAction;

    public string $commandSignature = 'bookings:cancel-expired';

    public string $commandDescription = 'Cancel pending bookings whose hold has expired';

    /**
     * Cancels expired pending bookings and expires their unpaid payments.
     * Runs from the scheduler without a tenant, so it reads every tenant on purpose.
     *
     * @return int number of cancelled bookings
     */
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $bookingIds = Booking::withoutGlobalScope(TenantScope::class)
                ->where('status', BookingStatus::PENDING)
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->pluck('id');

            if ($bookingIds->isEmpty()) {
                return 0;
            }

            Payment::withoutGlobalScope(TenantScope::class)
                ->whereIn('booking_id', $bookingIds)
                ->where('status', PaymentStatus::UNPAID)
                ->update(['status' => PaymentStatus::EXPIRED]);

            return Booking::withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $bookingIds)
                ->update(['status' => BookingStatus::CANCELLED]);
        });
    }

    public function asCommand(Command $command): void
    {
        $command->info("Cancelled {$this->handle()} expired pending booking(s).");
    }
}
