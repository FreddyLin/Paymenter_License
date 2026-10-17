<?php

namespace Paymenter\Extensions\Servers\Licenses\Models;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    protected $table = 'ext_licenses';

    protected $fillable = [
        'service_id',
        'user_id',
        'product_id',
        'license_key',
        'status',
        'expires_at',
        'validation_count',
        'last_validated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(LicenseValidation::class);
    }

    /**
     * Whether this license currently grants access.
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return !$this->expires_at || $this->expires_at->isFuture();
    }
}
