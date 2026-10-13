<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Master Region';

    protected static ?string $pluralModelLabel = 'Master Data Region';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Region Operasional')
                    ->description('Data wilayah / region kerja yang terhubung langsung dengan data karyawan dan pengajuan klaim.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Region')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Contoh: CIREBON / JATENG / JABODETABEK')
                            ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string)$state))),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Region (Singkatan)')
                            ->maxLength(20)
                            ->placeholder('Contoh: CRB / JTG / JBD')
                            ->dehydrateStateUsing(fn ($state) => $state ? strtoupper(trim((string)$state)) : null),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Wilayah / Catatan')
                            ->placeholder('Contoh: Mencakup wilayah operasional Semarang, Solo, dan sekitarnya')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Region')
                    ->weight('bold')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Jumlah Karyawan')
                    ->counts('employees')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}
