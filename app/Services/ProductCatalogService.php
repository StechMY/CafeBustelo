<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductCatalogService
{
    public function availableProducts(): Collection
    {
        return Product::query()
            ->active()
            ->orderBy('price')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Product $product): bool => $product->isPurchasableNow())
            ->values();
    }

    public function groupedAvailableProducts(): array
    {
        $products = $this->availableProducts();
        $productsByType = $products->groupBy('product_type');

        return [
            'products' => $products,
            'productsByType' => $productsByType,
            'productTypeTabs' => $productsByType
                ->map(fn (Collection $items, string $type): array => [
                    'key' => $type,
                    'label' => Product::typeLabel($type),
                    'count' => $items->count(),
                ])
                ->values(),
        ];
    }
}
