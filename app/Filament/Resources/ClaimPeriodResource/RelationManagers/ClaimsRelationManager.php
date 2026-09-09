<?php

namespace App\Filament\Resources\ClaimPeriodResource\RelationManagers;

use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    protected static ?string $title = 'Daftar Transaksi Klaim Periode Ini';

    protected static ?string $modelLabel = 'Transaksi Klaim';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('claim_type')
                            ->label('Jenis Biaya')
                            ->options([
                                'ENTERTAIN' => 'ENTERTAIN',
                                'BBM' => 'BBM',
                                'TRANSPORTASI' => 'TRANSPORTASI',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('amount')
                            ->label('Nominal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->placeholder('Contoh: 87000'),
                    ]),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan')
                            ->required()
                            ->placeholder('Contoh: MEETING DENGAN ASM PURWOKERTO'),
                        Forms\Components\TextInput::make('note')
                            ->label('Detail / Tempat / Toko')
                            ->placeholder('Contoh: WARUNG MAKAN SUMBER REJEKI SURABAYA'),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label('Pilih Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $branch = Branch::find($state);
                                    if ($branch) {
                                        $set('brand', $branch->brand);
                                        $set('reffnote', $branch->reffnote);
                                        if ($branch->city) {
                                            $set('city', $branch->city);
                                        }
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('brand')
                            ->label('Brand')
                            ->placeholder('Contoh: REALME'),
                        Forms\Components\TextInput::make('reffnote')
                            ->label('Reffnote')
                            ->placeholder('Contoh: REALME PURWOKERTO'),
                    ]),
                Forms\Components\TextInput::make('city')
                    ->label('Kota Transaksi')
                    ->placeholder('Contoh: Cilacap / Majenang / Semarang'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('purpose')
            ->columns([
                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('claim_type')
                    ->label('Jenis Biaya')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ENTERTAIN' => 'warning',
                        'BBM' => 'info',
                        'TRANSPORTASI' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Keperluan')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->purpose),
                Tables\Columns\TextColumn::make('note')
                    ->label('Detail Tempat')
                    ->limit(25)
                    ->default('-'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Realisasi')),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Brand')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('reffnote')
                    ->label('Reffnote')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->label('Kota')
                    ->default('-'),
                Tables\Columns\IconColumn::make('has_attendance')
                    ->label('Daftar Hadir')
                    ->boolean()
                    ->state(fn ($record) => $record->attendanceDoc()->exists()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('claim_type')
                    ->label('Jenis Biaya')
                    ->options([
                        'ENTERTAIN' => 'ENTERTAIN',
                        'BBM' => 'BBM',
                        'TRANSPORTASI' => 'TRANSPORTASI',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Transaksi Klaim')
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->recalculateTotals();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->recalculateTotals();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->recalculateTotals();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($livewire) {
                            $livewire->getOwnerRecord()->recalculateTotals();
                        }),
                ]),
            ]);
    }
}
