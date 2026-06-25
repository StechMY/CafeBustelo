<?php

namespace App\Http\Resources\Api;

use App\Support\Api\ApiFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownlineProductItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_name' => $this->resource['product_name'],
            'product_code' => $this->resource['product_code'],
            'status' => $this->resource['status'],
            'price' => ApiFormatter::money($this->resource['price']),
            'income_per_claim' => ApiFormatter::money($this->resource['income_per_claim']),
            'total_claims' => (int) $this->resource['total_claims'],
            'claimed_count' => (int) $this->resource['claimed_count'],
            'claim_interval_hours' => (int) $this->resource['claim_interval_hours'],
            'estimated_total_income' => ApiFormatter::money($this->resource['estimated_total_income']),
            'rate' => ApiFormatter::money($this->resource['rate']),
            'commission_per_claim' => ApiFormatter::money($this->resource['commission_per_claim']),
            'claimed_commission_estimate' => ApiFormatter::money($this->resource['claimed_commission_estimate']),
            'earning' => ApiFormatter::money($this->resource['earning']),
            'estimated_full_cycle_commission' => ApiFormatter::money($this->resource['estimated_full_cycle_commission']),
            'purchased_at' => ApiFormatter::dateTime($this->resource['purchased_at']),
            'purchased_at_label' => $this->resource['purchased_at_label'],
        ];
    }
}
