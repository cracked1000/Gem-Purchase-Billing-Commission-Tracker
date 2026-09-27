<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'serial_number',
        'price_mode',
        'verification_actions',
        'stone_notes',
        'description',
        'carats',
        'pieces',
        'unit_price',
        'amount',
        'export_category',
    ];

    protected function casts(): array
    {
        return [
            'carats' => 'decimal:2',
            'pieces' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'verification_actions' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
