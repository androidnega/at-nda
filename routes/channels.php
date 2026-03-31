<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Authenticated private channels (optional). Public attendance channels use
| Illuminate\Broadcasting\Channel in SessionLiveEvent and do not require auth.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
