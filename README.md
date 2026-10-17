# Licenses for Paymenter

Sell software licenses (e.g. Minecraft plugins, desktop software) with an
online validation API and versioned file downloads.

## Features

- Automatic license generation when an order is paid, lifecycle-aware
  (suspend/unsuspend/terminate follow the service)
- Configurable key format (`PREFIX-XXXX-XXXX-XXXX`, no ambiguous characters)
- Customer-facing license key display with self-service regeneration
- Online validation API with optional HMAC-signed responses
- Versioned downloads: upload files or link external URLs (GitHub Releases,
  S3, ...), with changelogs, publish toggle and download counters
- Admin area to manage licenses and files, including validation statistics
- Purchase email includes the license key

## Requirements

- Paymenter (Laravel 11+ / PHP 8.1+)
- No extra PHP extensions required

## Installation

1. In the admin panel, go to **Extensions** and upload the ZIP file.
2. Go to **Servers** and create a new server with the "Licenses" extension.
   Configure the key format and optionally an API signing secret.
3. Link a product to the Licenses server. Customers get a license as soon
   as their order is paid.

The extension ships its own database migrations (`ext_licenses`,
`ext_license_files`); they are applied automatically during installation.

## Configuration

Server settings:

| Setting | Description |
|---|---|
| `key_prefix` | Prefix of generated keys, e.g. `LIC` |
| `key_groups` | Number of character groups after the prefix |
| `key_group_length` | Characters per group |
| `signing_secret` | Optional. When set, all API responses are signed (see below) |

Product settings (per product, both optional):

| Setting | Description |
|---|---|
| `max_activations` | How often a key can be activated (bound to a domain). `0` = unlimited |
| `require_domain` | When enabled, validation must include an activated `domain` |

## Admin area

- **Licenses** (`/admin/licenses`): all licenses with customer, product,
  status, expiry and validation statistics. Status can be changed manually.
  The edit page includes the activation list and the validation log for
  the selected license.
- **License files** (`/admin/license-files`): versioned downloads per product.
  Provide either a file upload or an external URL, plus version, changelog and
  publish state. `filename` and file size are filled automatically. Creating
  a published file emails all customers with an active license.
- **License statistics** (navigation group Shop): overview of licenses,
  activations, validations (24h/7d), downloads and top files.

## Client area

The service page shows the license key, its expiry and a
**Regenerate license key** button (invalidates the old key and issues a new
one). If files exist for the product, a **Downloads** tab lists all published
versions with changelogs.

## Validation API

```
POST /extensions/licenses/validate
Content-Type: application/x-www-form-urlencoded

license_key=LIC-XXXX-XXXX-XXXX
```

Response:

```json
{
    "valid": true,
    "status": "active",
    "expires_at": "2027-10-17T00:00:00+00:00",
    "product": "Moveblocks",
    "service_id": 11
}
```

- `valid` is `false` for unknown keys, suspended/terminated licenses and
  expired licenses.
- `status` is one of `active`, `suspended`, `terminated`, `unknown`.
- Unknown keys are answered with `valid: false, status: unknown`.
- Every validation of a known key is written to a validation log
  (visible per license in the admin area).
- The endpoints are rate limited (validate/latest: 60 requests/minute,
  activate/deactivate: 30 requests/minute per IP).

### Activation / deactivation (optional binding)

```
POST /extensions/licenses/activate
license_key=LIC-XXXX-XXXX-XXXX&domain=example.com

→ {"activated": true, "domain": "example.com", "activations": 1, "max_activations": 3}
```

- `max_activations` (product setting) limits how often a key can be
  activated; `require_domain` makes the `domain` parameter mandatory.
- Re-activating the same domain is idempotent and does not consume a slot.
- `deactivate` frees a slot:

```
POST /extensions/licenses/deactivate
license_key=LIC-XXXX-XXXX-XXXX&domain=example.com

→ {"deactivated": true}
```

- When `require_domain` is enabled, `validate` additionally accepts a
  `domain` parameter and only returns `valid: true` for activated domains.

### Version check / auto-update

```
GET /extensions/licenses/latest?license_key=...&current_version=1.0.0

→ {
    "update_available": true,
    "version": "1.0.1",
    "changelog": "Fixed block placement",
    "published_at": "2026-10-17T20:00:00+00:00",
    "filename": "Moveblocks-1.0.1.jar",
    "size": 113982,
    "download_url": "https://your-store.com/extensions/licenses/download/2?license_key=...&expires=...&signature=..."
}
```

- `download_url` for external files is the configured URL; for uploaded
  files it is a short-lived signed URL (requires `signing_secret`).
- Compare `update_available` using `version_compare` against your
  current version; simply request the `download_url` to pull the file.

### Signed responses (optional)

When `signing_secret` is configured, every response includes an
`X-Signature` header: `hex(HMAC-SHA256(raw JSON body, signing_secret))`.
Clients should verify it to detect tampered or forged responses:

```php
$response = /* PSR-18 or Guzzle response */;
$expected = hash_hmac('sha256', (string) $response->getBody(), $signingSecret);
if (!hash_equals($expected, $response->getHeaderLine('X-Signature'))) {
    throw new Exception('Invalid signature');
}
```

## Lifecycle behaviour

| Paymenter event | License |
|---|---|
| Order paid / service created | Key generated, expiry synced with service |
| Service suspended / unsuspended | License suspended / reactivated |
| Service terminated | License terminated (key stays valid-looking in history but rejects validation) |
| Upgrade / renewal | Expiry re-synced, key unchanged |
| Regenerate button (client area) | Old key terminated, new key issued |

## Uninstall

Removing the extension drops the `ext_licenses` and `ext_license_files`
tables. Export anything you want to keep beforehand.

## Changelog

### 1.3.0
- Optional activation limits and domain binding per product
  (activate/deactivate API, domain-aware validation)
- Version check API with signed, short-lived download URLs for auto-updaters
- Validation log per license (IP, timestamp, result) with admin relation views
- Email to customers when a new file version is published
- Email when a license key is regenerated
- Admin statistics page (licenses, activations, validations, downloads)
- Client area translated (English/German, `resources/lang`)

### 1.2.0
- Admin area for licenses (view, status changes, statistics)
- Purchase email includes the license key
- Optional HMAC-signed validation responses (`X-Signature`)

### 1.1.0
- Versioned file downloads (upload or external URL)
- Downloads tab in the client area, license-gated download route

### 1.0.0
- Initial release: license generation, lifecycle hooks, validation API,
  client area key display with regeneration
