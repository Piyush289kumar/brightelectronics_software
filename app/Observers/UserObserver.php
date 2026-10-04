<?php

namespace App\Observers;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class UserObserver
{

    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        $employeeNumber = User::count() + 1;

        $joiningDate = $user->joining_date ?? now();

        $month = $joiningDate->format('m');
        $year = $joiningDate->format('y');

        $user->user_code =
            str_pad($employeeNumber, 3, '0', STR_PAD_LEFT)
            . $month
            . $year;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $receipent = Auth::user();
        Notification::make()
            ->title('User Created')
            ->body("Some String")
            ->sendToDatabase($receipent);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
