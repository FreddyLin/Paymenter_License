<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ValidationsRelationManager extends RelationManager
{
    protected static string $relationship = 'validations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('valid')
                    ->label('Valid')
                    ->boolean(),
                TextColumn::make('ip')
                    ->label('IP'),
                TextColumn::make('created_at')
                    ->label('Validated at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(20);
    }
}
