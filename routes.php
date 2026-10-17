<?php

use App\Helpers\ExtensionHelper;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Paymenter\Extensions\Servers\Licenses\Models\License;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseActivation;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseFile;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseValidation;

/**
 * Resolve a license from a request key, normalizing casing.
 */
function licenses_find(Request $request): ?License
{
    $key = strtoupper(trim((string) $request->input('license_key')));

    return License::where('license_key', $key)->first();
}

/**
 * Product-level settings (max_activations, require_domain) of a license.
 */
function licenses_product_settings(License $license): array
{
    $settings = $license->service?->product?->settings;

    return $settings ? ExtensionHelper::settingsToArray($settings) : [];
}

Route::post('/extensions/licenses/validate', function (Request $request) {
    $validated = $request->validate([
        'license_key' => 'required|string',
        'domain' => 'nullable|string',
    ]);

    $license = licenses_find($request);

    if (!$license) {
        return licenses_signed_response([
            'valid' => false,
            'status' => 'unknown',
        ]);
    }

    $valid = $license->isValid();

    // Domain binding: when the product requires it, the request must
    // include a domain that has been activated for this license.
    $domain = strtolower(trim((string) $request->input('domain')));
    if ($valid && (licenses_product_settings($license)['require_domain'] ?? false)) {
        $valid = $domain !== ''
            && $license->activations()
                ->where('domain', $domain)
                ->exists();
    }

    LicenseValidation::create([
        'license_id' => $license->id,
        'valid' => $valid,
        'ip' => $request->ip(),
    ]);

    $license->forceFill([
        'validation_count' => $license->validation_count + 1,
        'last_validated_at' => now(),
    ])->save();

    return licenses_signed_response([
        'valid' => $valid,
        'status' => $license->status,
        'expires_at' => $license->expires_at?->toIso8601String(),
        'product' => $license->product?->name,
        'service_id' => $license->service_id,
    ]);
})->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('extensions.servers.licenses.validate');

Route::post('/extensions/licenses/activate', function (Request $request) {
    $validated = $request->validate([
        'license_key' => 'required|string',
        'domain' => 'nullable|string',
        'ip' => 'nullable|string',
    ]);

    $license = licenses_find($request);

    if (!$license || !$license->isValid()) {
        return licenses_signed_response([
            'activated' => false,
            'reason' => 'invalid_license',
        ]);
    }

    $settings = licenses_product_settings($license);
    $domain = strtolower(trim((string) $request->input('domain')));

    if (($settings['require_domain'] ?? false) && !$domain) {
        return licenses_signed_response([
            'activated' => false,
            'reason' => 'domain_required',
        ]);
    }

    $maxActivations = (int) ($settings['max_activations'] ?? 0);
    $activations = $license->activations();

    if ($domain) {
        $existing = $license->activations()->where('domain', $domain)->first();

        if ($existing) {
            // Re-activating a known domain is idempotent
            return licenses_signed_response([
                'activated' => true,
                'domain' => $existing->domain,
                'activations' => $activations->count(),
                'max_activations' => $maxActivations,
            ]);
        }
    }

    if ($maxActivations > 0 && $activations->count() >= $maxActivations) {
        return licenses_signed_response([
            'activated' => false,
            'reason' => 'limit_reached',
            'activations' => $activations->count(),
            'max_activations' => $maxActivations,
        ]);
    }

    LicenseActivation::create([
        'license_id' => $license->id,
        'domain' => $domain ?: null,
        'ip' => $request->input('ip') ?: $request->ip(),
    ]);

    return licenses_signed_response([
        'activated' => true,
        'domain' => $domain ?: null,
        'activations' => $activations->count(),
        'max_activations' => $maxActivations,
    ]);
})->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('extensions.servers.licenses.activate');

Route::post('/extensions/licenses/deactivate', function (Request $request) {
    $validated = $request->validate([
        'license_key' => 'required|string',
        'domain' => 'required|string',
    ]);

    $license = licenses_find($request);

    if (!$license) {
        return licenses_signed_response([
            'deactivated' => false,
            'reason' => 'invalid_license',
        ]);
    }

    $domain = strtolower(trim((string) $request->input('domain')));

    $deleted = $license->activations()->where('domain', $domain)->delete();

    return licenses_signed_response([
        'deactivated' => (bool) $deleted,
    ]);
})->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('extensions.servers.licenses.deactivate');

Route::get('/extensions/licenses/latest', function (Request $request) {
    $validated = $request->validate([
        'license_key' => 'required|string',
        'current_version' => 'nullable|string',
    ]);

    $license = licenses_find($request);

    if (!$license || !$license->isValid()) {
        return licenses_signed_response([
            'error' => 'invalid_license',
        ]);
    }

    $file = LicenseFile::where('product_id', $license->product_id)
        ->published()
        ->orderByDesc('created_at')
        ->first();

    if (!$file) {
        return licenses_signed_response([
            'update_available' => false,
            'version' => null,
        ]);
    }

    $currentVersion = (string) $request->input('current_version');

    return licenses_signed_response([
        'update_available' => $currentVersion === '' || version_compare($file->version, $currentVersion, '>'),
        'version' => $file->version,
        'changelog' => $file->changelog,
        'published_at' => $file->created_at?->toIso8601String(),
        'filename' => $file->filename,
        'size' => $file->size,
        'download_url' => licenses_download_url($file, $license),
    ]);
})->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('extensions.servers.licenses.latest');

/**
 * A short-lived signed download URL for API clients, when a signing
 * secret is configured.
 */
function licenses_download_url(LicenseFile $file, License $license): ?string
{
    if (!$file->isUpload()) {
        return $file->external_url;
    }

    $ext = new \Paymenter\Extensions\Servers\Licenses\Licenses;
    $secret = $ext->signingSecret();
    if (!$secret) {
        return null;
    }

    $expires = now()->addHour()->timestamp;

    return route('extensions.servers.licenses.download', $file->id) . '?' . http_build_query([
        'license_key' => $license->license_key,
        'expires' => $expires,
        'signature' => hash_hmac('sha256', $file->id . '|' . $license->license_key . '|' . $expires, $secret),
    ]);
}

Route::get('/extensions/licenses/download/{file}', function (Request $request, LicenseFile $file) {
    abort_if(!$file->is_published, 404);

    // Signed API access (no session)
    $signature = $request->query('signature');
    if ($signature) {
        $secret = (new \Paymenter\Extensions\Servers\Licenses\Licenses)->signingSecret();
        $expected = $file->id . '|' . $request->query('license_key') . '|' . $request->query('expires');

        if (!$secret
            || !hash_equals(hash_hmac('sha256', $expected, $secret), $signature)
            || (int) $request->query('expires') < now()->timestamp) {
            abort(403);
        }

        $license = licenses_find($request);
        abort_if(!$license || !$license->isValid(), 403);
    } else {
        // Client area access (session)
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $hasAccess = License::where('user_id', $user->id)
            ->where('status', License::STATUS_ACTIVE)
            ->whereHas('service', function ($query) use ($file) {
                $query->where('status', 'active')->where('product_id', $file->product_id);
            })
            ->get()
            ->contains(fn (License $license) => $license->isValid());

        abort_if(!$hasAccess, 403);
    }

    $file->increment('download_count');

    if (!$file->isUpload()) {
        return redirect()->away($file->external_url);
    }

    abort_if(!$file->disk_path || !Storage::disk('local')->exists($file->disk_path), 404);

    return Storage::disk('local')->download($file->disk_path, $file->filename);
})->middleware('web')
    ->name('extensions.servers.licenses.download');

/**
 * JSON response with an X-Signature header (HMAC-SHA256 over the raw JSON
 * body) when a signing secret is configured on the Licenses server.
 */
if (!function_exists('licenses_signed_response')) {
    function licenses_signed_response(array $payload)
    {
        $secret = (new \Paymenter\Extensions\Servers\Licenses\Licenses)->signingSecret();

        $response = response()->json($payload);

        if ($secret) {
            $response->header('X-Signature', hash_hmac('sha256', $response->getContent(), $secret));
        }

        return $response;
    }
}
