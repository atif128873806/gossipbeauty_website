<?php

namespace Tests\Unit;

use App\Content\Catalog;
use PHPUnit\Framework\TestCase;

/**
 * Guards the price and time rules the whole demo depends on
 * (see AGENTS.md):
 * a "from" price is never a total, a quote is never an amount, and no duration
 * is ever invented.
 */
class CatalogTest extends TestCase
{
    public function test_a_fixed_price_is_shown_as_an_exact_amount(): void
    {
        $this->assertSame('CAD 105', Catalog::priceLabel([
            'price_mode' => 'fixed',
            'price_minor' => 10500,
        ]));
    }

    public function test_a_from_price_keeps_its_basis(): void
    {
        $this->assertSame('from CAD 90', Catalog::priceLabel([
            'price_mode' => 'from',
            'price_minor' => 9000,
        ]));
    }

    public function test_a_service_without_an_amount_is_quoted_rather_than_priced(): void
    {
        $this->assertSame('Quoted at booking', Catalog::priceLabel([
            'price_mode' => 'quote',
            'price_minor' => null,
        ]));
    }

    public function test_a_free_service_is_labelled_free_not_zero(): void
    {
        $this->assertSame('Free', Catalog::priceLabel([
            'price_mode' => 'free',
            'price_minor' => 0,
        ]));
    }

    public function test_amounts_with_cents_keep_them(): void
    {
        $this->assertSame('CAD 99.50', Catalog::formatCad(9950));
    }

    public function test_duration_is_absent_when_it_is_not_configured(): void
    {
        $this->assertNull(Catalog::durationLabel(['duration_minutes' => null]));
        $this->assertNull(Catalog::durationLabel([]));
    }

    public function test_duration_is_readable_at_any_length(): void
    {
        $this->assertSame('45 min', Catalog::durationLabel(['duration_minutes' => 45]));
        $this->assertSame('1 hr', Catalog::durationLabel(['duration_minutes' => 60]));
        $this->assertSame('1 hr 30 min', Catalog::durationLabel(['duration_minutes' => 90]));
        $this->assertSame('4 hr', Catalog::durationLabel(['duration_minutes' => 240]));
    }

    public function test_unknown_service_slugs_do_not_resolve(): void
    {
        $this->assertNull(Catalog::findService('not-a-real-service'));
        $this->assertNull(Catalog::findService(null));
        $this->assertNull(Catalog::findService(''));

        $this->assertSame('balayage', Catalog::findService('balayage')['slug']);
    }

    /** Every service row needs the fields the canonical record requires (04). */
    public function test_every_service_row_is_complete(): void
    {
        foreach (Catalog::catalog() as $service) {
            $this->assertNotSame('', $service['slug']);
            $this->assertNotSame('', $service['name']);
            $this->assertNotSame('', $service['result']);
            $this->assertContains($service['category_slug'], array_column(Catalog::categories(), 'slug'));
            $this->assertContains($service['price_mode'], ['fixed', 'from', 'free', 'quote']);
            $this->assertIsBool($service['signature']);
        }
    }

    public function test_service_slugs_are_unique(): void
    {
        $slugs = array_column(Catalog::catalog(), 'slug');

        $this->assertSame($slugs, array_unique($slugs));
    }

    /** Signature services lead with nails and stay within the Home page range. */
    public function test_home_preview_captions_come_from_the_gallery(): void
    {
        $captions = Catalog::workPreview();
        $galleryCaptions = array_column(Catalog::workGallery(), 'caption');

        $this->assertSame(6, count($captions));

        foreach ($captions as $caption) {
            $this->assertContains($caption, $galleryCaptions);
        }
    }

    public function test_signature_services_are_a_small_nails_first_set(): void
    {
        $signature = Catalog::signatureServices();

        $this->assertGreaterThanOrEqual(4, count($signature));
        $this->assertLessThanOrEqual(6, count($signature));
        $this->assertSame('nails', $signature[0]['category_slug']);
        $this->assertContains('brows', array_column($signature, 'category_slug'));
    }

    /** Gallery slots carry a caption and a real category, and no duplicates. */
    public function test_every_gallery_slot_is_complete(): void
    {
        $categorySlugs = array_column(Catalog::categories(), 'slug');
        $slugs = [];

        foreach (Catalog::workGallery() as $tile) {
            $this->assertNotSame('', $tile['slug']);
            $this->assertNotSame('', $tile['caption']);
            $this->assertContains($tile['category_slug'], $categorySlugs);
            $slugs[] = $tile['slug'];
        }

        $this->assertSame($slugs, array_unique($slugs));
    }

    public function test_gallery_can_be_filtered_and_limited_for_the_home_preview(): void
    {
        $brows = Catalog::workTiles('brows');

        foreach ($brows as $tile) {
            $this->assertSame('brows', $tile['category_slug']);
        }

        $this->assertGreaterThanOrEqual(4, count(Catalog::workTiles(null, 6)));
        $this->assertLessThanOrEqual(6, count(Catalog::workTiles(null, 6)));
        $this->assertSame([], Catalog::workTiles('not-a-category'));
    }

    /** The rating is attributed and dated, and never a quoted review (04). */
    public function test_rating_summary_is_attributed_and_dated(): void
    {
        $rating = Catalog::ratingSummary();

        $this->assertSame('DIKIDI', $rating['platform']);
        $this->assertNotSame('', $rating['value']);
        $this->assertNotSame('', $rating['count']);
        $this->assertNotSame('', $rating['observed']);
        $this->assertStringStartsWith('https://', $rating['url']);
    }

    public function test_add_ons_are_separate_records_from_services(): void
    {
        $serviceSlugs = array_column(Catalog::catalog(), 'slug');

        $this->assertNotSame([], Catalog::addOns());

        foreach (Catalog::addOns() as $addOn) {
            $this->assertNotContains($addOn['slug'], $serviceSlugs, 'An add-on must not also be a service row.');
            $this->assertContains($addOn['price_mode'], ['fixed', 'from', 'quote']);
        }
    }
}
