<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Owner instruction (Atif, 25 September 2026): the cinematic redesign may use the
 * configured licensed photography across Home, Services, Work, and booking.
 * Images still render through the shared photo component and carry their declared
 * alt text, dimensions, and loading hints.
 */
class PhotographyScopeTest extends TestCase
{
    public function test_home_page_shows_photography(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertGreaterThanOrEqual(6, substr_count($html, '<img'), 'The home page carries the site photography.');
        $this->assertStringContainsString('images/', $html);
    }

    public function test_services_work_and_booking_pages_use_configured_photography(): void
    {
        foreach (['/services', '/work', '/booking'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('<img', escape: false);
            $response->assertSee('images/', escape: false);
        }
    }

    public function test_every_public_photograph_declares_alt_text_size_and_loading_hint(): void
    {
        foreach (['/', '/services', '/work', '/booking'] as $path) {
            $html = $this->get($path)->getContent();
            preg_match_all('/<img\b[^>]*>/', $html, $matches);

            $this->assertNotEmpty($matches[0], "Expected the {$path} page to render configured photography.");

            foreach ($matches[0] as $tag) {
                $this->assertStringContainsString('alt="', $tag, "A photograph is missing its alt text: {$tag}");
                $this->assertMatchesRegularExpression('/width="\d+"/', $tag, "A photograph is missing its width: {$tag}");
                $this->assertMatchesRegularExpression('/height="\d+"/', $tag, "A photograph is missing its height: {$tag}");
                $this->assertMatchesRegularExpression('/loading="(eager|lazy)"/', $tag, "A photograph is missing its loading hint: {$tag}");
            }
        }
    }
}
