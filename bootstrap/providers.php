<?php

/*
|--------------------------------------------------------------------------
| Temple Trust Management System — Provider Registration
|--------------------------------------------------------------------------
|
| This file is the canonical source of truth for service provider order.
| Providers listed here are loaded by bootstrap/app.php and executed
| during the Application::register phase.
|
| Order is significant:
|   1. AppServiceProvider        — application-wide bindings
|   2. SharedServiceProvider     — base contracts and abstractions
|   3. (Future) module providers — Payments, Donations, ...
|
| Future phases must extend this list without reordering existing entries.
*/

return [
    App\Providers\AppServiceProvider::class,
    App\Shared\Providers\SharedServiceProvider::class,
];