<?php

namespace Database\Factories;

use App\Models\BillableService;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLineItem> */
class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5000, 100000);
        $quantity = fake()->randomFloat(3, 1, 3);
        $subtotal = round($unitPrice * $quantity, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'charge_id' => null,
            'billable_service_id' => BillableService::factory(),
            'service_price_id' => ServicePrice::factory(),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_subtotal' => $subtotal,
            'discount_amount' => 0,
            'adjustment_amount' => 0,
            'line_total' => $subtotal,
            'currency' => 'UGX',
        ];
    }
}
