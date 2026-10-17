<?php

namespace Paymenter\Extensions\Servers\Licenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivation extends Model
{
    protected $table = 'ext_license_activations';

    protected $fillable = [
        'license_id',
        'domain',
        'ip',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
