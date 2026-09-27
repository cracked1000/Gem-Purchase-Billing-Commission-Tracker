<?php

namespace App\Support;

use App\Models\Invoice;

class WhatsAppMessage
{
    /**
     * Public receipt for the SELLER — no commission, no phone number.
     * This is what we send to the seller so they know what they sold and the total.
     */
    public static function forSeller(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'seller']);

        $lines = [];
        $lines[] = '💎 *' . config('gemdesk.company_name') . '*';
        $lines[] = 'Purchase Receipt — ' . $invoice->invoice_number;
        $lines[] = 'Date: ' . $invoice->sold_at->setTimezone('Asia/Colombo')->format('d M Y');
        $lines[] = str_repeat('─', 30);
        $lines[] = 'Seller: *' . $invoice->seller->name . '*';
        $lines[] = '';
        $lines[] = '*Gems Purchased:*';

        $i = 1;
        foreach ($invoice->items as $item) {
            $desc = ($item->serial_number ? 'Serial ' . $item->serial_number . ' · ' : '') . $item->description;
            if (! empty($item->verification_actions)) {
                $desc .= ' · ' . strtoupper(implode(' + ', $item->verification_actions));
            }
            if ($item->stone_notes) {
                $desc .= ' · Note: ' . $item->stone_notes;
            }
            $weight = $item->carats !== null
                ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') . ' ct'
                : null;
            $priceLabel = $item->carats !== null
                ? Lkr::format($item->unit_price) . '/ct'
                : Lkr::format($item->unit_price) . '/pc';

            $detail = $i++ . '. ' . $desc;
            if ($weight) {
                $detail .= ' | ' . $item->pieces . ' pcs | ' . $weight . ' | ' . $priceLabel;
            } else {
                $detail .= ' | ' . $item->pieces . ' pcs | ' . $priceLabel;
            }
            $detail .= ' = *' . Lkr::format($item->amount) . '*';
            $lines[] = $detail;
        }

        $lines[] = str_repeat('─', 30);
        $lines[] = 'Total pieces: ' . $invoice->total_pieces;
        $lines[] = '*Total Amount: ' . Lkr::format($invoice->subtotal) . '*';
        $lines[] = '';
        $lines[] = '_This is your purchase receipt. Thank you!_';

        return implode("\n", $lines);
    }

    /**
     * Full internal receipt — includes commission, net payable, all details.
     * This version is for our own records / internal use.
     */
    public static function forInternal(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'seller']);

        $lines = [];
        $lines[] = '💎 *' . config('gemdesk.company_name') . '* — INTERNAL COPY';
        $lines[] = 'Bill: ' . $invoice->invoice_number;
        $lines[] = 'Date: ' . $invoice->sold_at->setTimezone('Asia/Colombo')->format('d M Y');
        $lines[] = str_repeat('─', 30);
        $lines[] = 'Seller: *' . $invoice->seller->name . '*';
        if ($invoice->seller->phone) {
            $lines[] = 'Phone: ' . $invoice->seller->phone;
        }
        $lines[] = '';
        $lines[] = '*Gems:*';

        $i = 1;
        foreach ($invoice->items as $item) {
            $desc = ($item->serial_number ? 'Serial ' . $item->serial_number . ' · ' : '') . $item->description;
            if (! empty($item->verification_actions)) {
                $desc .= ' · ' . strtoupper(implode(' + ', $item->verification_actions));
            }
            if ($item->stone_notes) {
                $desc .= ' · Note: ' . $item->stone_notes;
            }
            $weight = $item->carats !== null
                ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') . ' ct'
                : null;
            $priceLabel = $item->carats !== null
                ? Lkr::format($item->unit_price) . '/ct'
                : Lkr::format($item->unit_price) . '/pc';

            $detail = $i++ . '. ' . $desc;
            if ($weight) {
                $detail .= ' | ' . $item->pieces . ' pcs | ' . $weight . ' | ' . $priceLabel;
            } else {
                $detail .= ' | ' . $item->pieces . ' pcs | ' . $priceLabel;
            }
            $detail .= ' = ' . Lkr::format($item->amount);
            $lines[] = $detail;
        }

        $pct = rtrim(rtrim(number_format((float) $invoice->commission_percent, 2), '0'), '.');

        $lines[] = str_repeat('─', 30);
        $lines[] = 'Subtotal: ' . Lkr::format($invoice->subtotal);
        $lines[] = 'Commission (' . $pct . '%): − ' . Lkr::format($invoice->commission_amount);
        $lines[] = '*Net payable to seller: ' . Lkr::format($invoice->net_payable) . '*';
        $lines[] = '';
        $lines[] = 'Payment: ' . ucfirst($invoice->status);

        return implode("\n", $lines);
    }

    /**
     * @deprecated Use forSeller() instead. Kept for backwards compatibility.
     */
    public static function forInvoice(Invoice $invoice): string
    {
        return self::forSeller($invoice);
    }
}
