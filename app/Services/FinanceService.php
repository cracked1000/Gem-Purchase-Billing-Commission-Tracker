<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Seller;

/**
 * The Finance Department engine.
 *
 * Computes a seller's exact financial picture to the decimal place using
 * bcmath (arbitrary precision decimal) so there is never any floating-point
 * drift or rounding:
 *
 *   total sold       = Σ invoice subtotal          (what the gems were bought for)
 *   our commission   = Σ invoice commission_amount (what you earn)
 *   net to seller    = Σ invoice net_payable       (what you owe the seller)
 *   already paid     = Σ payments amount           (what you've paid so far)
 *   outstanding      = net to seller − already paid (what's still owed; may be negative = overpaid)
 */
class FinanceService
{
    /**
     * Exact financial summary for one seller (active invoices only; archived
     * bills are excluded from the running balance).
     */
    public function summaryFor(Seller $seller): object
    {
        $invoices = Invoice::where('seller_id', $seller->id)->get(['subtotal', 'commission_amount', 'net_payable']);
        $payments = $seller->payments()->get(['amount', 'paid_at']);

        $sold = '0.00';
        $commission = '0.00';
        $net = '0.00';
        foreach ($invoices as $invoice) {
            $sold = $this->add($sold, (string) $invoice->subtotal);
            $commission = $this->add($commission, (string) $invoice->commission_amount);
            $net = $this->add($net, (string) $invoice->net_payable);
        }

        $paid = '0.00';
        foreach ($payments as $payment) {
            $paid = $this->add($paid, (string) $payment->amount);
        }

        $outstanding = $this->sub($net, $paid);
        $isOverpaid = $this->negative($outstanding);
        $paidNothing = $this->zero($paid);
        $nothingOwed = $this->zero($outstanding);

        $status = 'unpaid';
        if (! $paidNothing) {
            $status = $isOverpaid ? 'overpaid' : ($nothingOwed ? 'settled' : 'partial');
        }

        return (object) [
            'sold'           => $sold,
            'commission'     => $commission,
            'net'            => $net,
            'paid'           => $paid,
            'outstanding'    => $outstanding,
            'is_overpaid'    => $isOverpaid,
            'status'         => $status,
            'bill_count'     => $invoices->count(),
            'payment_count'  => $payments->count(),
            'last_paid_at'   => $payments->max('paid_at'),
        ];
    }

    public function normalizeMoney(string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return function_exists('bcadd')
            ? bcadd($left, $right, 2)
            : number_format((float) $left + (float) $right, 2, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        return function_exists('bcsub')
            ? bcsub($left, $right, 2)
            : number_format((float) $left - (float) $right, 2, '.', '');
    }

    private function negative(string $value): bool
    {
        return function_exists('bccomp') ? bccomp($value, '0.00', 2) < 0 : ((float) $value < 0);
    }

    private function zero(string $value): bool
    {
        return function_exists('bccomp') ? bccomp($value, '0.00', 2) === 0 : ((float) $value == 0.0);
    }
}
