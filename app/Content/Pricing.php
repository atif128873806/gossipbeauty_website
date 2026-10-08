<?php

namespace App\Content;

/**
 * The price summary shown in the booking review step.
 *
 * The rules it enforces are the product rules
 * summarized in AGENTS.md:
 *
 *  - Fixed-price items add up to an exact total.
 *  - A `from` item makes the whole result an estimate that keeps its "from" basis; a
 *    minimum that has been added up is never presented as what the customer will pay.
 *  - A `quote` item is never shown as an amount at all, whatever it is added to.
 *  - Free items are labelled free and add nothing.
 *
 * The server recomputes this from the canonical catalog at confirmation and a customer's
 * earlier figure is never trusted (05, invariant 4). Until that transaction exists, the same
 * rule is applied to the catalog so the review step cannot drift from the cards on the site.
 */
class Pricing
{
    /**
     * @param  array<int, array<string, mixed>>  $items  service and add-on rows
     * @return array{label: string, value: string, note: ?string}
     */
    public static function summary(array $items): array
    {
        if ($items === []) {
            return [
                'label' => 'Price',
                'value' => 'Quoted at booking',
                'note' => null,
            ];
        }

        $total = 0;
        $hasFrom = false;
        $hasQuote = false;
        $chargeable = false;

        foreach ($items as $item) {
            $mode = $item['price_mode'] ?? 'quote';
            $amount = $item['price_minor'] ?? null;

            if ($mode === 'quote' || $amount === null) {
                $hasQuote = true;

                continue;
            }

            $amount = (int) $amount;
            $total += $amount;

            if ($mode === 'from') {
                $hasFrom = true;
            }

            if ($mode !== 'free' && $amount > 0) {
                $chargeable = true;
            }
        }

        if ($hasQuote) {
            return [
                'label' => 'Price',
                'value' => 'Quoted at booking',
                'note' => 'The studio confirms this price when you book.',
            ];
        }

        if (! $chargeable) {
            return [
                'label' => 'Total',
                'value' => 'Free',
                'note' => null,
            ];
        }

        if ($hasFrom) {
            return [
                'label' => 'Estimated total',
                'value' => 'from '.(string) Catalog::formatCad($total),
                'note' => 'One of these is priced from a minimum, so the final amount is confirmed at your appointment.',
            ];
        }

        return [
            'label' => 'Total',
            'value' => (string) Catalog::formatCad($total),
            'note' => 'Extras and services that are charged separately are itemised above.',
        ];
    }
}
