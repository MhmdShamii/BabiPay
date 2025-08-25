<?php


namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUUID
{

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }
}
