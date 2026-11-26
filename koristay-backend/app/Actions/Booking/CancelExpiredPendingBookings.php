<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelExpiredPendingBookings
{
    use AsAction;

    public string $commandSignature = 'bookings:cancel-expired';

    public string $commandDescription = 'Cancel pending bookings whose hold has expired';

    /**
     * Runs from the scheduler without a tenant, so it reads every tenant on purpose.
     *
     * @return int number of cancelled bookings
     */
    public function handle(): int
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('status', BookingStatus::PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => BookingStatus::CANCELLED]);
    }

    public function asCommand(Command $command): void
    {
        $command->info("Cancelled {$this->handle()} expired pending booking(s).");
    }
}
