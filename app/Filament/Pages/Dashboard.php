<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Periode Dashboard')
                    ->description('Pilih bulan dan tahun untuk memfilter seluruh statistik klaim dan sisa budget')
                    ->icon('heroicon-o-funnel')
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                'all' => 'Semua Bulan (Sepanjang Tahun)',
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ])
                            ->default((int)now()->month)
                            ->selectablePlaceholder(false),

                        Select::make('year')
                            ->label('Tahun')
                            ->options(function () {
                                $currentYear = (int)now()->year;
                                $years = [];
                                for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                                    $years[$y] = (string)$y;
                                }
                                return $years;
                            })
                            ->default((int)now()->year)
                            ->selectablePlaceholder(false),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ]),
            ]);
    }
}
