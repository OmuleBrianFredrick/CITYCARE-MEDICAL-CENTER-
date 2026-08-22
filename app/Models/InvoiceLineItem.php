<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'charge_id', 'billable_service_id', 'service_price_id', 'description', 'quantity', 'unit_price', 'line_subtotal', 'discount_amount', 'adjustment_amount', 'line_total', 'currency'])]
class InvoiceLineItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function charge(): BelongsTo { return $this->belongsTo(Charge::class); }
    public function billableService(): BelongsTo { return $this->belongsTo(BillableService::class); }
    public function servicePrice(): BelongsTo { return $this->belongsTo(ServicePrice::class); }
}