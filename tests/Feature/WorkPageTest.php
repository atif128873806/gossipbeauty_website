<?php

namespace Tests\Feature;

use App\Content\Catalog;
use Tests\TestCase;

class WorkPageTest extends TestCase
{
    public function test_work_page_renders_for_a_visitor(): void
    {
        $response = $this->get('/work');

        $response->assertOk();
        $response->assertSee('Our work &amp; reviews', escape: false);
    }

    public function test_every_gallery_slot_is_listed_with_its_caption(): void
    {
        $response = $this->get('/work');

        foreach (Catalog::workGallery() as $tile) {
            $response->assertSee($tile['caption'], escape: false);
        }
    }

    public function test_category_filter_narrows_the_gallery(): void
    {
        $category = Catalog::workGallery()[0]['category_slug'];
        $response = $this->get('/work?category='.$category);

        $response->assertOk();
        foreach (Catalog::workGallery() as $tile) {
            if ($tile['category_slug'] === $category) {
                $response->assertSee($tile['caption'], escape: false);
            } else {
                $response->assertDontSee($tile['caption'], escape: false);
            }
        }
    }

    public function test_unknown_category_is_ignored_rather_than_breaking_the_page(): void
    {
        $response = $this->get('/work?category=not-a-category');

        $response->assertOk();
        foreach (Catalog::workGallery() as $tile) {
            $response->assertSee($tile['caption'], escape: false);
        }
    }

    public function test_only_categories_that_hold_work_are_offered_as_filters(): void
    {
        $response = $this->get('/work');

        $withWork = array_unique(array_column(Catalog::workGallery(), 'category_slug'));

        foreach (Catalog::categories() as $category) {
            $url = route('work', ['category' => $category['slug']]);

            if (in_array($category['slug'], $withWork, true)) {
                $response->assertSee($url, escape: false);
            } else {
                $response->assertDontSee($url, escape: false);
            }
        }
    }

    /**
     * The exact service behind a look is unconfirmed, so the action must send the
     * visitor to that category's services rather than name a service (03).
     */
    public function test_each_slot_offers_a_similar_look_action_into_its_category(): void
    {
        $response = $this->get('/work');

        foreach (Catalog::workGallery() as $tile) {
            $response->assertSee(route('services', ['category' => $tile['category_slug']]), escape: false);
            $response->assertSee(
                'aria-label="Book a similar look: '.$tile['caption'].', '.$tile['category'].' services"',
                escape: false
            );
        }
    }

    /** Platform scores remain attributed separately and include an observed date. */
    public function test_reviews_section_attributes_both_ratings_to_their_platforms_and_dates(): void
    {
        $rating = Catalog::ratingSummary();
        $googleRating = Catalog::googleRatingSummary();

        $response = $this->get('/work');

        $response->assertSee($rating['value'], escape: false);
        $response->assertSee($rating['count'], escape: false);
        $response->assertSee($rating['observed'], escape: false);
        $response->assertSee($rating['platform'], escape: false);
        $response->assertSee($googleRating['value'], escape: false);
        $response->assertSee($googleRating['count'], escape: false);
        $response->assertSee($googleRating['observed'], escape: false);
        $response->assertSee($googleRating['platform'], escape: false);
        $response->assertSee(route('services'), escape: false);
    }

    public function test_review_link_is_outbound_and_labelled(): void
    {
        $rating = Catalog::ratingSummary();
        $googleRating = Catalog::googleRatingSummary();

        $response = $this->get('/work');

        $response->assertSee($rating['url'], escape: false);
        $response->assertSee($googleRating['url'], escape: false);
        $response->assertSee('rel="nofollow noopener"', escape: false);
        $response->assertSee('Read reviews on '.$rating['platform'], escape: false);
        $response->assertSee('Read reviews on '.$googleRating['platform'], escape: false);
    }

    /**
     * No customer words are reproduced without permission, so the section says where
     * the reviews actually live instead of printing inventable testimonials (04).
     */
    public function test_google_reviews_link_to_their_original_source_without_reprinting_user_content(): void
    {
        $this->get('/work')
            ->assertSee('Read reviews on Google', escape: false)
            ->assertSee('Read reviews on DIKIDI', escape: false)
            ->assertDontSee('<blockquote', escape: false);
    }

    public function test_primary_navigation_marks_the_work_page_as_current(): void
    {
        $this->get('/work')->assertSee('aria-current="page"', escape: false);
    }

    public function test_work_page_carries_no_sample_or_unverified_placeholder_wording(): void
    {
        $text = strip_tags($this->get('/work')->getContent());

        foreach (['sample', 'demo', 'demonstration', 'concept', 'to confirm', 'to be supplied', 'not approved', 'placeholder', 'lorem', 'coming soon'] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $text);
        }
    }

    public function test_the_reviews_link_to_both_platforms_that_publish_them(): void
    {
        $this->get('/work')
            ->assertSee('Read reviews on DIKIDI', escape: false)
            ->assertSee('Read reviews on Google', escape: false);
    }
}
