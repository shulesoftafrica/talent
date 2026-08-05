<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Makes a model resolve/generate routes by its uuid column instead of the
 * sequential integer id — used by anything whose id might otherwise show
 * up in a candidate-facing URL (e.g. /app/applications?selected=11).
 * Sequential ids invite enumeration/probing even when the underlying
 * route is properly ownership-checked; a uuid gives nothing away.
 *
 * Every route(...) call across the app that passes a model instance (the
 * existing convention everywhere in this codebase) picks this up for
 * free via Eloquent's getRouteKey(), no call-site changes needed. Only
 * places that pass a raw ->id instead of the model itself need updating.
 */
trait HasUuidRouteKey
{
    protected static function bootHasUuidRouteKey(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
