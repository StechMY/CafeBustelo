<?php

namespace App\Http\Resources\Api;

use App\Support\Api\ApiFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownlineProductGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => (int) $this->resource['user_id'],
            'username' => $this->resource['username'],
            'name' => $this->resource['name'],
            'phone' => $this->resource['phone'],
            'level' => (int) $this->resource['level'],
            'level_label' => $this->resource['level_label'],
            'rate' => ApiFormatter::money($this->resource['rate']),
            'product_count' => (int) $this->resource['product_count'],
            'total_sales' => ApiFormatter::money($this->resource['total_sales']),
            'total_income' => ApiFormatter::money($this->resource['total_income']),
            'total_estimated_commission' => ApiFormatter::money($this->resource['total_estimated_commission']),
            'total_claimed_commission_estimate' => ApiFormatter::money($this->resource['total_claimed_commission_estimate']),
            'latest_purchase_at' => ApiFormatter::dateTime($this->resource['latest_purchase_at']),
            'latest_purchase_at_label' => $this->resource['latest_purchase_at_label'],
            'products' => DownlineProductItemResource::collection(collect($this->resource['products']))->resolve($request),
        ];
    }
}
