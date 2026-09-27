<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'invoice_number',
        'seller_id',
        'sold_at',
        'commission_percent',
        'subtotal',
        'commission_amount',
        'net_payable',
        'total_pieces',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'date',
            'commission_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'total_pieces' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
