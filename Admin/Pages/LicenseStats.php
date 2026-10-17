<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Pages;

use Filament\Pages\Page;
use Paymenter\Extensions\Servers\Licenses\Models\License;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseFile;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseValidation;

class LicenseStats extends Page
{
    protected string $view = 'licenses::stats';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-bar-chart-2-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-bar-chart-2-fill';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 32;

    public static function getNavigationLabel(): string
    {
        return 'License statistics';
    }

    public function getTitle(): string
    {
        return 'License statistics';
    }

    public function getViewData(): array
    {
        $topFiles = LicenseFile::query()
            ->orderByDesc('download_count')
            ->with('product')
            ->limit(5)
            ->get();

        return [
            'active' => License::where('status', License::STATUS_ACTIVE)->count(),
            'suspended' => License::where('status', License::STATUS_SUSPENDED)->count(),
            'terminated' => License::where('status', License::STATUS_TERMINATED)->count(),
            'activations' => \Paymenter\Extensions\Servers\Licenses\Models\LicenseActivation::count(),
            'validations24h' => LicenseValidation::where('created_at', '>=', now()->subDay())->count(),
            'validations7d' => LicenseValidation::where('created_at', '>=', now()->subDays(7))->count(),
            'downloads' => LicenseFile::sum('download_count'),
            'files' => LicenseFile::count(),
            'topFiles' => $topFiles,
        ];
    }
}
