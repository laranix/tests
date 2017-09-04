<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \Laranix\Auth\Email\Events\Updated::class => [
            \Laranix\Auth\Email\Listeners\Updated::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \Laranix\Auth\Listeners\Logout::class,
        ],
        \Illuminate\Auth\Events\Registered::class => [
            \Laranix\Auth\Listeners\Registered::class,
        ],
        \Laranix\Auth\Password\Events\Updated::class => [
            \Laranix\Auth\Password\Listeners\Updated::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        \Laranix\Auth\Email\Verification\Events\Subscriber::class,
        \Laranix\Auth\Events\Login\Subscriber::class,
        \Laranix\Auth\Group\Events\Subscriber::class,
        \Laranix\Auth\Password\Reset\Events\Subscriber::class,
        \Laranix\Auth\User\Cage\Events\Subscriber::class,
        \Laranix\Auth\User\Events\Subscriber::class,
        \Laranix\Auth\User\Groups\Events\Subscriber::class,
    ];
    
    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
