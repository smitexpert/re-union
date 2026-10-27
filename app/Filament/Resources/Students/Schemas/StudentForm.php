<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\StudentOption;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(20),
                TextInput::make('graduation_year')
                    ->numeric()
                    ->integer()
                    ->minValue(1950)
                    ->maxValue(2100)
                    ->required(),
                Select::make('option')
                    ->label('Option')
                    ->options(StudentOption::options())
                    ->required(),
                TextInput::make('session')
                    ->maxLength(255),
                TextInput::make('current_occupation')
                    ->maxLength(150),
                Textarea::make('present_address')
                    ->columnSpanFull()
                    ->rows(2),
                Textarea::make('permanent_address')
                    ->columnSpanFull()
                    ->rows(2),
                Textarea::make('office_address')
                    ->label('Office/Business Address')
                    ->columnSpanFull()
                    ->rows(2),
            ]);
    }
}
