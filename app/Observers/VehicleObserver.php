<?php

namespace App\Observers;

use App\Models\Vehicle;

class VehicleObserver
{
    /**
     * Handle the Vehicle "creating" event.
     */
    public function creating(Vehicle $vehicle)
    {
        if (auth()->check()) {
            $vehicle->user_id = auth()->id();
        }
    }

    /**
     * Handle the Vehicle "updated" event.
     */
    public function updated(Vehicle $vehicle): void
    {
        //
    }

    /**
     * Handle the Vehicle "deleted" event.
     */
    public function deleted(Vehicle $vehicle): void
    {
        //
    }

    /**
     * Handle the Vehicle "restored" event.
     */
    public function restored(Vehicle $vehicle): void
    {
        //
    }

    /**
     * Handle the Vehicle "force deleted" event.
     */
    public function forceDeleted(Vehicle $vehicle): void
    {
        //
    }
}
