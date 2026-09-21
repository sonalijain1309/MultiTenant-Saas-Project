<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'billing_interval' => $this->billing_interval,
            'limits' => [
                'max_users' => $this->max_users,
                'max_customers' => $this->max_customers,
                'max_products' => $this->max_products,
                'max_orders_per_month' => $this->max_orders_per_month,
            ],
            'features' => $this->features,
        ];
    }
}
