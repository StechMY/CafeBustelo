<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Models\Contact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Customer Service 1'),

                                Select::make('type')
                                    ->label('Type')
                                    ->required()
                                    ->default(Contact::TYPE_WHATSAPP)
                                    ->options([
                                        Contact::TYPE_WHATSAPP => 'WhatsApp',
                                        Contact::TYPE_TELEGRAM => 'Telegram',
                                        Contact::TYPE_EMAIL => 'Email',
                                        Contact::TYPE_PHONE => 'Phone',
                                        Contact::TYPE_LINK => 'Link',
                                    ])
                                    ->native(false)
                                    ->live(),

                                TextInput::make('link')
                                    ->label('Link')
                                    ->required()
                                    ->maxLength(2048)
                                    ->url()
                                    ->placeholder('https://wa.me/60123456789')
                                    ->helperText('Use full URL. Example: https://wa.me/60123456789'),

                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->default(Contact::STATUS_ACTIVE)
                                    ->options([
                                        Contact::STATUS_ACTIVE => 'Active',
                                        Contact::STATUS_INACTIVE => 'Inactive',
                                    ])
                                    ->native(false),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}