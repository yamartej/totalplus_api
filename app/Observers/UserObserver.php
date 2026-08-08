<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\CustomVerifyEmail;

class UserObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function created(User $user)
    {
        //$user->notify(new CustomVerifyEmail);
    }
}
