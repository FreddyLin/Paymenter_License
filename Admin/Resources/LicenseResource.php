<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseResource\Pages\EditLicense;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseResource\Pages\ListLicenses;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseResource\RelationManagers\ActivationsRelationManager;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseResource\RelationManagers\ValidationsRelationManager;
use Paymenter\Extensions\Servers\Licenses\Models\License;

class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-key-2-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-key-2-fill';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 31;

    public static function getLabel(): string
    {
        return 'License';
    }

    public static function getPluralLabel(): string
    {
        return 'Licenses';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('license_key')
                    ->label('License key')
                    ->disabled()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        License::STATUS_ACTIVE => 'Active',
                        License::STATUS_SUSPENDED => 'Suspended',
                        License::STATUS_TERMINATED => 'Terminated',
                    ])
                    ->required(),
                TextInput::make('expires_at')
                    ->label('Expires at')
                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('Y-m-d H:i') : null)
                    ->disabled(),
                TextInput::make('validation_count')
                    ->label('Validations')
                    ->disabled(),
                TextInput::make('last_validated_at')
                    ->label('Last validation')
                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('Y-m-d H:i') : null)
                    ->disabled(),
                TextInput::make('user.email')
                    ->label('Customer')
                    ->disabled(),
                TextInput::make('product.name')
                    ->label('Product')
                    ->disabled(),
                TextInput::make('service_id')
                    ->label('Service ID')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_key')
                    ->label('License key')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        License::STATUS_ACTIVE => 'success',
                        License::STATUS_SUSPENDED => 'warning',
                        License::STATUS_TERMINATED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('service_id')
                    ->label('Service')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('validation_count')
                    ->label('Validations')
                    ->sortable(),
                TextColumn::make('last_validated_at')
                    ->label('Last validation')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        License::STATUS_ACTIVE => 'Active',
                        License::STATUS_SUSPENDED => 'Suspended',
                        License::STATUS_TERMINATED => 'Terminated',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
            'edit' => EditLicense::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ActivationsRelationManager::class,
            ValidationsRelationManager::class,
        ];
    }
}
