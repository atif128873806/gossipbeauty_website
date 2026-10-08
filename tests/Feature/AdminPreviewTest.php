<?php

namespace Tests\Feature;

use App\Content\PanelDay;
use Tests\TestCase;

/**
 * The panel screen exists to show the layout of the appointment panel Gossips will own,
 * and must never look like the working panel: it says what it is, it performs no action and
 * it holds no real data. The live panel is the authenticated /admin route.
 */
class AdminPreviewTest extends TestCase
{
    public function test_the_panel_screen_renders(): void
    {
        $response = $this->get('/admin/preview');

        $response->assertOk();
        $response->assertSee('Today', escape: false);
        $response->assertSee(PanelDay::dayLabel(), escape: false);
        $response->assertSee('Toronto time', escape: false);
    }

    public function test_it_states_plainly_what_the_screen_is(): void
    {
        $response = $this->get('/admin/preview');

        $response->assertSee('Panel preview', escape: false);
        $response->assertSee('This screen is an illustrative layout only and does not read or change appointments.', escape: false);
    }

    /** Nothing on the screen may act, so there are no enabled controls or forms at all. */
    public function test_the_screen_performs_no_action(): void
    {
        $response = $this->get('/admin/preview');

        $response->assertOk();
        $response->assertDontSee('<form', escape: false);
        $response->assertDontSee('<input', escape: false);
        $response->assertDontSee('wire:click', escape: false);
        $response->assertDontSee('<select', escape: false);

        // Anything button-shaped is disabled and labelled as such for assistive technology.
        $html = $response->getContent();

        preg_match_all('/<button[^>]*>/', $html, $buttons);

        $this->assertNotSame([], $buttons[0], 'The screen shows the actions for layout.');
        foreach ($buttons[0] as $button) {
            $this->assertStringContainsString('disabled', $button);
            $this->assertStringContainsString('aria-disabled="true"', $button);
        }
    }

    /** The panel screen must not be indexed as if it were a live admin area. */
    public function test_the_panel_screen_is_not_indexable(): void
    {
        $this->get('/admin/preview')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
    }

    public function test_every_appointment_is_presented_with_its_status_written_out(): void
    {
        $response = $this->get('/admin/preview');

        foreach (PanelDay::blocks() as $block) {
            // Default escaping, because service names contain apostrophes and ampersands.
            $response->assertSee($block['name']);
            $response->assertSee($block['time_label'], escape: false);
        }

        foreach (PanelDay::statuses() as $label) {
            $response->assertSee($label, escape: false);
        }

        $response->assertSee('Every block also writes its status in words', escape: false);
    }

    public function test_the_summary_matches_the_day_it_draws(): void
    {
        $statistics = PanelDay::statistics();

        $response = $this->get('/admin/preview');

        $response->assertSee((string) $statistics['appointments'], escape: false);
        $response->assertSee($statistics['utilisation'].'% of the day', escape: false);
        $response->assertSee('Day view', escape: false);
    }

    /** The team's real names are unconfirmed, so columns are service areas. */
    public function test_it_does_not_invent_team_members_or_customers(): void
    {
        $response = $this->get('/admin/preview');

        foreach (PanelDay::columns() as $column) {
            $response->assertSee($column['label']);
        }

        $response->assertSee('Columns are service areas rather than named team members', escape: false);
        $response->assertSee('Client A', escape: false);
    }

    /** What the working panel will add is stated, so the preview cannot overpromise. */
    public function test_it_says_what_the_working_panel_adds(): void
    {
        $response = $this->get('/admin/preview');

        $response->assertSee('What the working panel adds', escape: false);
        $response->assertSee('Real appointments', escape: false);
        $response->assertSee('Team and opening hours', escape: false);
        $response->assertSee('Access and records', escape: false);
    }

    public function test_it_is_not_linked_from_the_public_pages(): void
    {
        foreach (['/', '/services', '/work', '/booking'] as $url) {
            $this->get($url)->assertDontSee('/admin/preview', escape: false);
        }
    }

    /** With the preview switched off the route is a plain 404, not a page about it. */
    public function test_the_panel_screen_disappears_when_the_preview_is_off(): void
    {
        config()->set('gossips.studio_panel.preview', false);

        $this->get('/admin/preview')->assertNotFound();
    }
}
