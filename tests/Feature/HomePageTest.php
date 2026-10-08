<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    /**
     * The established anchored section order:
     * signature services, categories, work, studio, reviews, visit. The hero is
     * checked separately because it is the page heading, not an anchor target.
     *
     * @var list<string>
     */
    private const SECTION_ORDER = [
        'signature-services',
        'services',
        'work',
        'studio',
        'reviews',
        'visit',
    ];

    public function test_home_page_renders_for_a_visitor(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gossips Beauty', escape: false);
    }

    public function test_home_page_follows_the_documented_section_order(): void
    {
        $html = $this->get('/')->getContent();

        $hero = strpos($html, '<h1');

        $this->assertNotFalse($hero, 'Home page is missing its hero heading.');
        $this->assertStringContainsString('Etobicoke', $html, 'The hero must name the studio location.');

        $positions = [];

        foreach (self::SECTION_ORDER as $section) {
            $position = strpos($html, 'id="'.$section.'"');

            $this->assertNotFalse($position, "Home page is missing the \"{$section}\" section.");

            $positions[$section] = $position;
        }

        $sorted = array_values($positions);
        sort($sorted);

        $this->assertSame(
            $positions,
            array_combine(self::SECTION_ORDER, $sorted),
            'Home sections must follow the established page order.'
        );

        $this->assertLessThan(
            $positions['signature-services'],
            $hero,
            'The hero must come before the first anchored section.'
        );
    }

    /**
     * Owner instruction (24 September 2026): public pages carry no "sample" or
     * "to confirm" wording. Unverified values are omitted rather than labelled,
     * so this guards the rule against creeping back in.
     */
    public function test_home_page_carries_no_sample_or_unverified_placeholder_wording(): void
    {
        $text = strip_tags($this->get('/')->getContent());

        foreach ([
            'sample',
            'demo',
            'demonstration',
            'concept',
            'to confirm',
            'to be supplied',
            'not approved',
            'placeholder',
            'lorem',
        ] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $text);
        }
    }

    /**
     * The hero shows confirmed facts only: the rating the studio publishes on its
     * own profile, and the address.
     */
    public function test_home_page_shows_the_published_rating_and_the_address(): void
    {
        $response = $this->get('/');

        $response->assertSee('5.0 / 5 on DIKIDI', escape: false);
        $response->assertSee('649 The Queensway', escape: false);
    }

    /**
     * Signature cards show the salon's published appointment time, and the
     * price beneath it keeps its "from" basis instead of reading as a total.
     */
    public function test_signature_cards_show_a_price_basis_and_a_time(): void
    {
        $response = $this->get('/');

        $response->assertSee('from CAD 90', escape: false);
        $response->assertSee('1 hr 30 min', escape: false);
    }

    public function test_home_page_links_to_the_services_page(): void
    {
        $response = $this->get('/');

        $response->assertSee(route('services'), escape: false);
        $response->assertSee(route('services', ['category' => 'nails']), escape: false);
    }

    public function test_booking_page_keeps_the_selected_service(): void
    {
        $this->enablePublicBookingForTest();
        $response = $this->get('/booking?service=signature-hard-gel-overlay');

        $response->assertOk();
        $response->assertSee('Signature Hard Gel Overlay', escape: false);
        $response->assertSee('Book an appointment', escape: false);
    }

    public function test_booking_page_rejects_an_unknown_service(): void
    {
        $response = $this->get('/booking?service=not-a-real-service');

        $response->assertOk();
        $response->assertDontSee('not-a-real-service', escape: false);
    }
}
