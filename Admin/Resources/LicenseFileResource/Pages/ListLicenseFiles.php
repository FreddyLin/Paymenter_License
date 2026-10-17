<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource;

class ListLicenseFiles extends ListRecords
{
    protected static string $resource = LicenseFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
