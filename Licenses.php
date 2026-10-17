<?php

namespace Paymenter\Extensions\Servers\Licenses;

use App\Classes\Extension\Server;
use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Paymenter\Extensions\Servers\Licenses\Models\License;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseFile;

#[\App\Attributes\ExtensionMeta(
    name: 'Licenses',
    description: 'Sell software licenses with an online validation API and file downloads',
    version: '1.3.0',
    author: 'Buster4126',
    url: 'https://luminanode.net',
)]
class Licenses extends Server
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('licenses', __DIR__ . '/resources/views');
        \Illuminate\Support\Facades\Lang::addNamespace('licenses', __DIR__ . '/resources/lang');
    }

    /**
     * Get all the configuration for the extension
     *
     * @param  array  $values
     * @return array
     */
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'key_prefix',
                'label' => 'License key prefix',
                'type' => 'text',
                'default' => 'LIC',
                'description' => 'Prefix of generated license keys, e.g. LIC-XXXX-XXXX-XXXX',
                'required' => true,
                'validation' => 'alpha_num',
            ],
            [
                'name' => 'key_groups',
                'label' => 'Key groups',
                'type' => 'number',
                'default' => 3,
                'description' => 'Number of character groups after the prefix',
                'required' => true,
                'validation' => 'integer|min:1|max:8',
            ],
            [
                'name' => 'key_group_length',
                'label' => 'Characters per group',
                'type' => 'number',
                'default' => 4,
                'description' => 'Number of characters per group (ambiguous characters like 0/O and 1/I are never used)',
                'required' => true,
                'validation' => 'integer|min:3|max:12',
            ],
            [
                'name' => 'signing_secret',
                'label' => 'API signing secret',
                'type' => 'text',
                'description' => 'Optional. When set, validate responses include an X-Signature header (HMAC-SHA256 over the raw JSON body) so clients can detect tampering.',
                'required' => false,
                'encrypted' => true,
            ],
        ];
    }

    /**
     * Get product config
     *
     * @param  array  $values
     * @return array
     */
    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'max_activations',
                'label' => 'Max activations per license',
                'type' => 'number',
                'default' => 0,
                'description' => 'How often a license key can be activated (bound to a domain). 0 = unlimited',
                'required' => true,
                'validation' => 'integer|min:0|max:1000',
            ],
            [
                'name' => 'require_domain',
                'label' => 'Require domain binding',
                'type' => 'checkbox',
                'default' => false,
                'description' => 'When enabled, validation requests must include a domain that has been activated for the license',
                'required' => false,
            ],
        ];
    }

    /**
     * Check if current configuration is valid
     */
    public function testConfig(): bool|string
    {
        $prefix = $this->config('key_prefix');
        if (!$prefix || !ctype_alnum((string) $prefix)) {
            return 'License key prefix must be alphanumeric';
        }

        return true;
    }

    /**
     * Called when the extension is installed for the first time
     */
    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Servers/Licenses/database/migrations');
    }

    /**
     * Called when the extension is uninstalled
     */
    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Servers/Licenses/database/migrations');
    }

    /**
     * Create a license for the service.
     *
     * @param  Service  $service  The service that is being created.
     * @param  array  $settings  The settings that are provided on the product from `getProductConfig`.
     * @param  array  $properties  Custom properties created by the admin and values coming from `getCheckoutConfig`.
     */
    public function createServer(Service $service, $settings, $properties)
    {
        $license = License::where('service_id', $service->id)
            ->where('status', '!=', License::STATUS_TERMINATED)
            ->first();

        if ($license) {
            // Reuse the existing key, only resync the expiry
            $license->update(['expires_at' => $service->expires_at]);

            return ['license_key' => $license->license_key];
        }

        $license = License::create([
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'product_id' => $service->product_id,
            'license_key' => $this->generateKey(),
            'status' => License::STATUS_ACTIVE,
            'expires_at' => $service->expires_at,
        ]);

        return ['license_key' => $license->license_key];
    }

    /**
     * The signing secret for API responses, if configured.
     */
    public function signingSecret(): ?string
    {
        $secret = $this->config('signing_secret');

        return $secret ?: null;
    }

    /**
     * Suspend a license
     */
    public function suspendServer(Service $service, $settings, $properties)
    {
        $this->license($service)?->update(['status' => License::STATUS_SUSPENDED]);

        return true;
    }

    /**
     * Unsuspend a license
     */
    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $this->license($service)?->update(['status' => License::STATUS_ACTIVE]);

        return true;
    }

    /**
     * Terminate a license
     */
    public function terminateServer(Service $service, $settings, $properties)
    {
        $this->license($service)?->update(['status' => License::STATUS_TERMINATED]);

        return true;
    }

    /**
     * Upgrade a license (keep the key, resync the expiry)
     */
    public function upgradeServer(Service $service, $settings, $properties)
    {
        $this->license($service)?->update(['expires_at' => $service->expires_at]);

        return true;
    }

    /**
     * Get the actions for the client area
     */
    public function getActions(Service $service, $settings, $properties): array
    {
        $license = $this->license($service);
        if (!$license) {
            return [];
        }

        $actions = [
            [
                'type' => 'text',
                'label' => __('licenses::messages.license_key'),
                'text' => $license->license_key,
            ],
        ];

        if ($license->expires_at) {
            $actions[] = [
                'type' => 'text',
                'label' => __('licenses::messages.expires_at'),
                'text' => $license->expires_at->format('Y-m-d H:i'),
            ];
        }

        $maxActivations = (int) ($settings['max_activations'] ?? 0);
        if ($maxActivations > 0) {
            $actions[] = [
                'type' => 'text',
                'label' => __('licenses::messages.activations'),
                'text' => $license->activations()->count() . ' / ' . $maxActivations,
            ];
        }

        $actions[] = [
            'type' => 'button',
            'label' => __('licenses::messages.regenerate'),
            'function' => 'regenerateLicense',
        ];

        $publishedFiles = LicenseFile::where('product_id', $service->product_id)
            ->published()
            ->count();

        if ($publishedFiles > 0) {
            $actions[] = [
                'type' => 'view',
                'name' => 'downloads',
                'label' => 'Downloads',
                'function' => 'downloadsView',
            ];
        }

        return $actions;
    }

    /**
     * The downloads view for the client area.
     */
    public function downloadsView(Service $service, $settings, $properties, $view = null)
    {
        $files = LicenseFile::where('product_id', $service->product_id)
            ->published()
            ->orderByDesc('created_at')
            ->get();

        return view('licenses::downloads', [
            'service' => $service,
            'files' => $files,
        ]);
    }

    /**
     * Invalidate the current key and issue a new one.
     * Called by the client area via the action button.
     */
    public function regenerateLicense(Service $service, $settings, $properties)
    {
        $license = $this->license($service);
        if (!$license) {
            throw new \Exception('No license found for this service');
        }

        $license->update([
            'status' => License::STATUS_TERMINATED,
        ]);

        License::create([
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'product_id' => $service->product_id,
            'license_key' => $this->generateKey(),
            'status' => License::STATUS_ACTIVE,
            'expires_at' => $service->expires_at,
        ]);

        $this->sendRegeneratedEmail($service);

        return route('services.show', $service->id);
    }

    /**
     * Inform the customer that their key was replaced.
     */
    private function sendRegeneratedEmail(Service $service): void
    {
        $user = $service->user;
        if (!$user || !$user->email) {
            return;
        }

        $license = $this->license($service);
        if (!$license) {
            return;
        }

        \App\Helpers\NotificationHelper::sendSystemEmailNotification(
            subject: "Your license key for {$service->product->name} has been regenerated",
            body: "# License key regenerated\n\n"
                . "Your previous license key is no longer valid. Your new license key for **{$service->product->name}** is:\n\n"
                . "`{$license->license_key}`\n\n"
                . "You can view it any time on your service page:\n"
                . route('services.show', $service->id),
            email: $user->email,
        );
    }

    /**
     * The active (non-terminated) license of a service, newest first.
     */
    private function license(Service $service): ?License
    {
        return License::where('service_id', $service->id)
            ->where('status', '!=', License::STATUS_TERMINATED)
            ->latest('id')
            ->first();
    }

    /**
     * Generate a license key in the format PREFIX-XXXX-XXXX-XXXX
     */
    private function generateKey(): string
    {
        $prefix = strtoupper((string) ($this->config('key_prefix') ?: 'LIC'));
        $groups = max(1, (int) ($this->config('key_groups') ?: 3));
        $length = max(3, (int) ($this->config('key_group_length') ?: 4));

        $parts = [$prefix];
        for ($i = 0; $i < $groups; $i++) {
            $part = '';
            for ($j = 0; $j < $length; $j++) {
                $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $parts[] = $part;
        }

        return implode('-', $parts);
    }
}
