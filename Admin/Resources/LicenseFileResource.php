<?php

namespace Paymenter\Extensions\Servers\Licenses\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages\CreateLicenseFile;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages\EditLicenseFile;
use Paymenter\Extensions\Servers\Licenses\Admin\Resources\LicenseFileResource\Pages\ListLicenseFiles;
use Paymenter\Extensions\Servers\Licenses\Models\LicenseFile;

class LicenseFileResource extends Resource
{
    protected static ?string $model = LicenseFile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-download-2-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-download-2-fill';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 30;

    public static function getLabel(): string
    {
        return 'License file';
    }

    public static function getPluralLabel(): string
    {
        return 'License files';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('version')
                    ->label('Version')
                    ->required()
                    ->maxLength(64)
                    ->placeholder('e.g. 1.0.0'),
                FileUpload::make('disk_path')
                    ->label('File upload')
                    ->disk('local')
                    ->directory('license-files')
                    ->visibility('private')
                    ->maxSize(102400)
                    ->live()
                    ->helperText('Upload a file from this device. Alternatively provide an external URL below.')
                    ->required(fn (Get $get): bool => blank($get('external_url'))),
                TextInput::make('external_url')
                    ->label('External URL')
                    ->url()
                    ->maxLength(65535)
                    ->live()
                    ->helperText('e.g. a GitHub Releases or S3 URL. Alternatively upload a file above.')
                    ->required(fn (Get $get): bool => blank($get('disk_path'))),
                TextInput::make('filename')
                    ->label('Download filename')
                    ->maxLength(255)
                    ->helperText('Leave empty to derive it from the upload or URL.'),
                Textarea::make('changelog')
                    ->label('Changelog')
                    ->columnSpanFull()
                    ->rows(5)
                    ->maxLength(65535),
                Toggle::make('is_published')
                    ->label('Published')
                    ->default(true)
                    ->helperText('Only published files are visible to customers.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('filename')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (LicenseFile $record): string => $record->filename),
                TextColumn::make('type')
                    ->label('Type')
                    ->state(fn (LicenseFile $record): string => $record->isUpload() ? 'Upload' : 'URL'),
                TextColumn::make('size')
                    ->label('Size')
                    ->state(fn (LicenseFile $record): ?string => $record->isUpload() ? $record->formattedSize() : null)
                    ->placeholder('-'),
                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Fill filename and size from the upload or URL.
     */
    public static function mutateFormData(array $data): array
    {
        if (!empty($data['disk_path'])) {
            $data['disk_path'] = is_array($data['disk_path']) ? ($data['disk_path'][0] ?? null) : $data['disk_path'];
            if (!empty($data['disk_path'])) {
                if (!Storage::disk('local')->exists($data['disk_path'])) {
                    throw new \Exception('The file could not be stored on the server (storage/app is not writable or the local disk failed silently). Check directory ownership/permissions and try again.');
                }
                if (empty($data['filename'])) {
                    $data['filename'] = basename($data['disk_path']);
                }
                $data['size'] = Storage::disk('local')->size($data['disk_path']);
            }
        } else {
            $data['disk_path'] = null;
        }

        if (!empty($data['external_url']) && empty($data['filename'])) {
            $path = parse_url($data['external_url'], PHP_URL_PATH) ?: '';
            $data['filename'] = basename($path) ?: 'download';
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenseFiles::route('/'),
            'create' => CreateLicenseFile::route('/create'),
            'edit' => EditLicenseFile::route('/{record}/edit'),
        ];
    }
}
