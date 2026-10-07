<?php

namespace App\Filament\Resources\PageImages;

use App\Filament\Resources\PageImages\Pages\ManagePageImages;
use App\Models\PageImage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PageImageResource extends Resource
{
    protected static ?string $model = PageImage::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('page_images.image');
    }

    public static function getPluralModelLabel(): string
    {
        return __('page_images.images');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('page')
                ->label(__('page_images.page'))
                ->options(PageImage::pageOptions())
                ->required(),
            TextInput::make('title')
                ->label(__('filament.title'))
                ->maxLength(255)
                ->required(),
            FileUpload::make('image_path')
                ->label(__('filament.image'))
                ->disk('do_spaces')
                ->visibility('public')
                ->directory('page-images')
                ->image()
                ->maxSize(10240)
                ->required()
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label(__('filament.sort_order'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required(),
            Toggle::make('is_enabled')
                ->label(__('filament.is_enabled'))
                ->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ImageEntry::make('image_path')
                ->label(__('filament.image'))
                ->disk('do_spaces')
                ->visibility('public')
                ->height(360)
                ->columnSpanFull(),
            TextEntry::make('page')
                ->label(__('page_images.page'))
                ->formatStateUsing(fn (string $state): string => PageImage::pageOptions()[$state]),
            TextEntry::make('title')->label(__('filament.title')),
            TextEntry::make('sort_order')->label(__('filament.sort_order')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('filament.image'))
                    ->disk('do_spaces')
                    ->visibility('public')
                    ->height(60)
                    ->width(100),
                TextColumn::make('page')
                    ->label(__('page_images.page'))
                    ->formatStateUsing(fn (string $state): string => PageImage::pageOptions()[$state])
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('filament.title'))
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable(),
                TextColumn::make('is_enabled')
                    ->label(__('filament.is_enabled'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('page_images.enabled') : __('page_images.disabled'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('page')
                    ->label(__('page_images.page'))
                    ->options(PageImage::pageOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePageImages::route('/')];
    }
}
