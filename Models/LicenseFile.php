<?php

namespace Paymenter\Extensions\Servers\Licenses\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LicenseFile extends Model
{
    protected $table = 'ext_license_files';

    protected $fillable = [
        'product_id',
        'version',
        'changelog',
        'filename',
        'disk_path',
        'external_url',
        'is_published',
        'size',
        'download_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (LicenseFile $file) {
            if ($file->disk_path && Storage::disk('local')->exists($file->disk_path)) {
                Storage::disk('local')->delete($file->disk_path);
            }
        });

        static::created(function (LicenseFile $file) {
            if ($file->is_published) {
                \Paymenter\Extensions\Servers\Licenses\Jobs\NotifyNewVersion::dispatch($file);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Whether a file was uploaded to the local disk.
     */
    public function isUpload(): bool
    {
        return $this->disk_path !== null;
    }

    /**
     * Human readable file size.
     */
    public function formattedSize(): ?string
    {
        if (!$this->size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1) . ' ' . $units[$i];
    }
}
