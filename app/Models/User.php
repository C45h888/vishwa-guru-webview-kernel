<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Placeholder Eloquent model.
 *
 * Phase 0.25 does not introduce persistent entities. This class exists so
 * that Laravel's `php artisan` commands have a default model namespace to
 * reference. It will be removed (or replaced) once the first business
 * entity is implemented in Phase 0.5.
 */
class User extends EloquentModel
{
    use HasFactory;

    protected $guarded = [];
}
