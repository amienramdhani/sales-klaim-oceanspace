<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetTypeResource\Pages;
use App\Models\BudgetType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BudgetTypeResource extends Resource
{
    protected static ?string $model = BudgetType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Jenis Budget';

    protected static ?string $pluralModelLabel = 'Jenis Budget';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kategori & Plafon Budget')
                    ->description('Kelola kategori biaya dan batas plafon anggaran sesuai peraturan perusahaan')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Jenis Budget')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Entertain / BBM / Transportasi / Perjalanan Dinas')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $set('code', strtoupper(Str::slug($state, '_')));
                                }
                            }),
                        Forms\Components\TextInput::make('code')
                            ->label('Kode / Inisial')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Contoh: ENTERTAIN / BBM / TRANSPORTASI')
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('amount')
                            ->label('Plafon Anggaran / Budget (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->required()
                            ->placeholder('Contoh: 2400000')
                            ->helperText('Nominal batas anggaran yang diperbolehkan untuk kategori biaya ini.'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Aktif' => 'Aktif',
                                'Nonaktif' => 'Nonaktif',
                            ])
                            ->default('Aktif')
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan / Aturan Penggunaan')
                            ->rows(2)
                            ->placeholder('Contoh: Untuk keperluan meeting dengan relasi/ASM/dealer...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Jenis Budget')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Plafon Anggaran')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->default('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
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
            'index' => Pages\ListBudgetTypes::route('/'),
            'create' => Pages\CreateBudgetType::route('/create'),
            'edit' => Pages\EditBudgetType::route('/{record}/edit'),
        ];
    }
}
