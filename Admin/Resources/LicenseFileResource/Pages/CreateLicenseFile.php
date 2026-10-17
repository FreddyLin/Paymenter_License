<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource;

class CreateLicenseFile extends CreateRecord
{
    protected static string $resource = LicenseFileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LicenseFileResource::mutateFormData($data);
    }
}
