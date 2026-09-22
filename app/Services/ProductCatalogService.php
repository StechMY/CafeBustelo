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
            // Keep daily sold-out products visible so customers can see their status.
            ->filter(fn (Product $product): bool => $product->isWithinAvailablePeriod()
                && $product->isAvailableToday()
                && $product->hasStock())
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
