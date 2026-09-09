<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PositionResource\Pages;
use App\Models\Position;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Data Jabatan & Budget';

    protected static ?string $pluralModelLabel = 'Data Jabatan & Budget';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Jabatan')
                    ->description('Tentukan nama jabatan dan deskripsi fungsi kerja')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Jabatan')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: RGM / ASM / Staff Operasional'),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi / Cakupan Tugas')
                            ->rows(2)
                            ->placeholder('Keterangan tanggung jawab wilayah atau tugas...'),
                    ]),

                Forms\Components\Section::make('Standar Plafon Budget Entertain')
                    ->description('Tentukan batas nominal budget entertain bulanan standar untuk jabatan ini (RGM: Rp 2.400.000, ASM: Rp 1.500.000). Budget lainnya (BBM, Perdin, Transport) diatur fleksibel per karyawan di Data Karyawan.')
                    ->schema([
                        Forms\Components\TextInput::make('entertain_budget')
                            ->label('Standar Budget Entertain Bulanan (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(0)
                            ->helperText('Contoh: RGM Rp 2.400.000, ASM Rp 1.500.000'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Jabatan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('entertain_budget')
                    ->label('Standar Budget Entertain')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Jumlah Karyawan')
                    ->counts('employees')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(30)
                    ->default('-'),
            ])
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
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
