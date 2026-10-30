<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TreasureChestClaims\TreasureChestClaimResource;
use App\Models\Product;
use App\Services\Settings\TreasureChestSettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use UnitEnum;

class TreasureChestConfiguration extends Page
{
    protected string $view = 'filament.pages.treasure-chest-configuration';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 27;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return TreasureChestClaimResource::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('treasure_chest.configuration');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public function getTitle(): string
    {
        return __('treasure_chest.configuration');
    }

    public function getSubheading(): ?string
    {
        return __('treasure_chest.configuration_help');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(app(TreasureChestSettings::class)->formData());
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make(__('treasure_chest.display_settings'))->schema([
                Toggle::make('show_rewards')->label(__('treasure_chest.show_rewards'))
                    ->helperText(__('treasure_chest.show_rewards_help')),
            ]),
            Section::make(__('treasure_chest.purchase_rules'))->description(__('treasure_chest.purchase_rules_help'))->schema([
                Repeater::make('products')->label(__('treasure_chest.product'))->defaultItems(0)->maxItems(100)
                    ->addActionLabel(__('treasure_chest.add_product_rule'))->reorderable(false)->columns(2)
                    ->schema([
                        Select::make('code')->label(__('treasure_chest.product'))->required()->searchable()
                            ->options(function (): array {
                                $options = collect(app(TreasureChestSettings::class)->rules()['products'])->pluck('code', 'code')->all();
                                foreach (Product::query()->orderBy('code')->get(['code', 'name', 'price']) as $product) {
                                    $options[$product->code] = $product->code.' · '.$product->name.' · '.number_format((float) $product->price).' VND';
                                }
                                return $options;
                            })->live()->afterStateUpdated(function ($state, Set $set): void {
                                if ($product = Product::query()->where('code', $state)->first()) {
                                    $set('price', $product->price);
                                }
                            }),
                        TextInput::make('keys')->label(__('treasure_chest.keys'))->integer()->required()->minValue(1)->maxValue(4294967295),
                        TextInput::make('price')->label(__('treasure_chest.price'))->numeric()->required()->minValue(0)->step(0.01)->suffix('VND'),
                        Toggle::make('match_weekend_price')->label(__('treasure_chest.match_weekend_price'))->default(false)
                            ->helperText(__('treasure_chest.match_weekend_price_help')),
                    ]),
            ]),
        ];
        foreach (range(1, 9) as $number) {
            $sections[] = Section::make($number === 9 ? __('treasure_chest.ultimate') : __('treasure_chest.chest_number', ['number' => $number]))
                ->statePath('prizes.'.$number)->columns(4)->schema([
                    TextInput::make('threshold')->label(__('treasure_chest.threshold'))->integer()->required()->minValue(1)->maxValue(4294967295),
                    TextInput::make('amount')->label(__('golden_egg.cash_amount'))->numeric()->required()->minValue(0)->step(0.01)->suffix('VND'),
                    TextInput::make('keys')->label(__('treasure_chest.reward_keys'))->integer()->required()->minValue(0)->maxValue(4294967295),
                    TextInput::make('discount_percent')->label(__('golden_egg.discount_percent'))->numeric()->required()->minValue(0)->maxValue(100)->step(0.01)->suffix('%'),
                ]);
        }

        return $schema->statePath('data')->components($sections);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        app(TreasureChestSettings::class)->save($this->form->getState());
        Notification::make()->title(__('golden_egg.settings_saved'))->success()->send();
    }
}
