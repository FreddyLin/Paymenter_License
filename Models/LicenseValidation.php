<?php

namespace Paymenter\Extensions\Servers\Licenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseValidation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ext_license_validations';

    protected $fillable = [
        'license_id',
        'valid',
        'ip',
    ];

    protected $casts = [
        'valid' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
