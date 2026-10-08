<?php

namespace Tests\Feature;

use App\Content\Catalog;
use Tests\TestCase;

class ServicesPageTest extends TestCase
{
    public function test_services_page_renders_for_a_visitor(): void
    {
        $response = $this->get('/services');

        $response->assertOk();
        $response->assertSee('Services &amp; pricing', escape: false);
    }

    /** Every browsing category in the catalog reaches the page as a filter (03). */
    public function test_every_category_is_offered_as_a_filter(): void
    {
        $response = $this->get('/services');

        foreach (Catalog::categories() as $category) {
            $response->assertSee($category['name'], escape: false);
            $response->assertSee(route('services', ['category' => $category['slug']]), escape: false);
        }
    }

    /** The whole menu is reachable, not only the Home page's signature six. */
    public function test_services_page_lists_every_service_in_the_catalog(): void
    {
        $response = $this->get('/services');

        foreach (Catalog::catalog() as $service) {
            $response->assertSee($service['name']);
        }
    }

    public function test_category_filter_narrows_the_list_to_that_category(): void
    {
        $response = $this->get('/services?category=makeup');

        $response->assertOk();
        $response->assertSee('Soft Glam Makeup', escape: false);
        $response->assertSee('Makeup + Blow Out', escape: false);

        // A service from another category must not survive the filter.
        $response->assertDontSee('Signature Hard Gel Overlay', escape: false);
        $response->assertDontSee('Brow Complex', escape: false);
    }

    public function test_unknown_category_is_ignored_rather_than_breaking_the_page(): void
    {
        $response = $this->get('/services?category=not-a-category');

        $response->assertOk();
        $response->assertSee('Signature Hard Gel Overlay', escape: false);
        $response->assertSee('Soft Glam Makeup', escape: false);
    }

    public function test_search_matches_the_service_a_customer_would_look_for(): void
    {
        $response = $this->get('/services?q=highlights');

        $response->assertOk();
        $response->assertSee('Half Head Highlights', escape: false);
        $response->assertSee('Face Frame Highlights', escape: false);
        $response->assertSee('Full Head Highlights', escape: false);

        $response->assertDontSee('Brow Tint', escape: false);
    }

    public function test_search_with_no_matches_shows_a_designed_empty_state(): void
    {
        $response = $this->get('/services?q=zzzznotaservice');

        $response->assertOk();
        $response->assertSee('No services match that search', escape: false);
        $response->assertSee(route('services'), escape: false);
    }

    /** The unfiltered page must not carry the empty state's heading. */
    public function test_empty_state_does_not_appear_on_the_full_list(): void
    {
        $this->get('/services')->assertDontSee('No services match that search', escape: false);
    }

    public function test_price_basis_is_shown_correctly_on_the_page(): void
    {
        $response = $this->get('/services?category=nails');

        // A "from" price keeps its basis and is never shown as a total.
        $response->assertSee('from CAD 90', escape: false);
        // A fixed price is shown as an exact amount.
        $response->assertSee('CAD 105', escape: false);
    }

    public function test_free_consultations_are_labelled_rather_than_priced_at_zero(): void
    {
        $response = $this->get('/services?q=consultation');

        $response->assertOk();
        $response->assertSee('Free', escape: false);
        $response->assertDontSee('CAD 0', escape: false);
    }

    public function test_duration_from_the_published_menu_is_shown(): void
    {
        $response = $this->get('/services?category=makeup');

        $response->assertSee('1 hr', escape: false);
        $response->assertSee('2 hr 30 min', escape: false);
    }

    /** Removal and design are add-ons with their own price and time (04). */
    public function test_nail_add_ons_are_listed_separately(): void
    {
        $response = $this->get('/services?category=nails');

        $response->assertSee('Nail add-ons', escape: false);
        $response->assertSee('Shellac removal only', escape: false);
        $response->assertSee('Cannot be combined with another service.', escape: false);
    }

    public function test_nail_add_ons_are_not_shown_under_other_categories(): void
    {
        $response = $this->get('/services?category=hair');

        $response->assertDontSee('Nail add-ons', escape: false);
    }

    /** Choosing a service carries it into booking without asking again (03). */
    public function test_service_choice_is_retained_into_booking(): void
    {
        $this->enablePublicBookingForTest();
        $this->get('/services?category=hair')
            ->assertSee(route('booking', ['service' => 'balayage']), escape: false);

        $booking = $this->get('/booking?service=balayage');

        $booking->assertOk();
        $booking->assertSee('Balayage', escape: false);
        $booking->assertSee('Book an appointment', escape: false);
    }

    public function test_primary_navigation_marks_the_services_page_as_current(): void
    {
        $this->get('/services')->assertSee('aria-current="page"', escape: false);
        $this->get('/')->assertDontSee('aria-current="page"', escape: false);
    }

    /**
     * Owner instruction (24 September 2026): page content carries no wording
     * about the build. The check runs against the visible text, not the markup,
     * so a functional attribute such as an input's placeholder hint is not
     * mistaken for build wording.
     */
    public function test_services_page_carries_no_sample_or_unverified_placeholder_wording(): void
    {
        $text = strip_tags($this->get('/services')->getContent());

        foreach (['sample', 'demo', 'demonstration', 'concept', 'to confirm', 'to be supplied', 'not approved', 'placeholder', 'lorem'] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $text);
        }
    }
}
