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
                Section::make(__('filament.contact_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(__('filament.customer_service_1')),

                                Select::make('type')
                                    ->label(__('filament.type'))
                                    ->required()
                                    ->default(Contact::TYPE_WHATSAPP)
                                    ->options([
                                        Contact::TYPE_WHATSAPP => __('filament.whatsapp'),
                                        Contact::TYPE_TELEGRAM => __('filament.telegram'),
                                        Contact::TYPE_ZALO => __('filament.zalo'),
                                        Contact::TYPE_EMAIL => __('filament.email'),
                                        Contact::TYPE_PHONE => __('filament.phone'),
                                        Contact::TYPE_LINK => __('filament.link'),
                                    ])
                                    ->native(false)
                                    ->live(),

                                TextInput::make('link')
                                    ->label(__('filament.link'))
                                    ->required()
                                    ->maxLength(2048)
                                    ->url()
                                    ->placeholder('https://wa.me/60123456789')
                                    ->helperText(__('filament.use_full_url_example_https_wa_me_60123456789')),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->required()
                                    ->default(Contact::STATUS_ACTIVE)
                                    ->options([
                                        Contact::STATUS_ACTIVE => __('filament.active'),
                                        Contact::STATUS_INACTIVE => __('filament.inactive'),
                                    ])
                                    ->native(false),

                                TextInput::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->numeric()
                                    ->default(0),

                                Textarea::make('description')
                                    ->label(__('filament.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}