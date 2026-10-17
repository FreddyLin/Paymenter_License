<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource;

class EditLicenseFile extends EditRecord
{
    protected static string $resource = LicenseFileResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LicenseFileResource::mutateFormData($data);
    }
}
