<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $model
 */
#[Table('settings')]
abstract class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->model = static::class;
        });

        static::addGlobalScope('model', function ($builder) {
            $builder->where('model', static::class);
        });
    }
}
