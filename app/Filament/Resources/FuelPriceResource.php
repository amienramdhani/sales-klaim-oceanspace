<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelPriceResource\Pages;
use App\Models\FuelPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FuelPriceResource extends Resource
{
    protected static ?string $model = FuelPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Harga BBM';

    protected static ?string $pluralModelLabel = 'Master Harga BBM';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('fuel_type')
                            ->label('Jenis BBM')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: Pertalite / Pertamax'),

                        Forms\Components\TextInput::make('price_per_liter')
                            ->label('Harga Resmi per Liter (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1)
                            ->helperText('Harga ini akan otomatis menjadi acuan dasar perhitungan liter dan konversi klaim BBM.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Hanya harga BBM yang aktif yang akan digunakan sebagai acuan sistem.'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan / Keterangan')
                            ->rows(2)
                            ->placeholder('Contoh: Harga resmi SPBU Pertamina subsidi per 2026')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fuel_type')
                    ->label('Jenis BBM')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Pertalite' => 'success',
                        'Pertamax' => 'warning',
                        'Solar' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price_per_liter')
                    ->label('Harga per Liter')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(50),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('fuel_type', 'asc')
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
            'index' => Pages\ListFuelPrices::route('/'),
            'create' => Pages\CreateFuelPrice::route('/create'),
            'edit' => Pages\EditFuelPrice::route('/{record}/edit'),
        ];
    }
}
