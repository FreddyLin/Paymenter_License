<?php

namespace Paymenter\Extensions\Servers\Licenses\Jobs;

use App\Helpers\NotificationHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Paymenter\Extensions\Servers\Licenses\Models\License;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseFile;

class NotifyNewVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 2;

    public function __construct(public LicenseFile $file) {}

    public function handle(): void
    {
        $product = $this->file->product;
        if (!$product || !$this->file->is_published) {
            return;
        }

        $licenses = License::query()
            ->where('product_id', $this->file->product_id)
            ->where('status', License::STATUS_ACTIVE)
            ->whereHas('service', fn ($query) => $query->where('status', 'active'))
            ->with(['user', 'service'])
            ->get();

        foreach ($licenses as $license) {
            $user = $license->user;
            if (!$user || !$user->email) {
                continue;
            }

            NotificationHelper::sendSystemEmailNotification(
                subject: "New version available for {$product->name}: v{$this->file->version}",
                body: "# New version available\n\n"
                    . "A new version of **{$product->name}** is available: **v{$this->file->version}**.\n\n"
                    . ($this->file->changelog ? "### Changelog\n\n{$this->file->changelog}\n\n" : '')
                    . "You can download it from your service page:\n"
                    . route('services.show', $license->service_id),
                email: $user->email,
            );
        }
    }
}
