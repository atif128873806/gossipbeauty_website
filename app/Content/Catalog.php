<?php

namespace App\Content;

use Illuminate\Support\Str;

/**
 * Legacy presentation metadata and the original imported service menu.
 *
 * Live service browsing and booking now read the database-backed catalog. Keep
 * this class only for presentation data still consumed by the public pages.
 *
 * Owner instruction (Atif, 24 September 2026): public pages carry no "sample",
 * "to confirm" or "not approved" wording. Anything unverified is omitted
 * rather than labelled; a page shows only what Gossips itself publishes.
 *
 * Source of the values below: Gossips' own public DIKIDI service list, read on
 * 24 September 2026 (https://dikidi.net/en/profile/gossips_beauty_674763/services).
 * Names, CAD amounts and appointment times are the salon's own published
 * figures, transcribed with their fixed / "from" / free basis kept intact:
 *
 *  - A "from" price is never shown as a total.
 *  - A service with no published amount is a `quote`: the page says it is
 *    quoted at booking and shows no number.
 *  - `duration_minutes` is the salon's published appointment time, not an
 *    estimate. If a future row has no confirmed duration the cards show no
 *    time at all rather than a guessed figure.
 *  - Removal and design are separate add-on records (`addOns()`), so differently
 *    priced or timed work is not merged into an ambiguous service row.
 *
 * Not invented here: review text, customer names, staff biographies, opening
 * hours, phone number and cancellation policy. Those need the owner, so they are
 * absent. Photography comes from config/gossips.php, which holds licensed stock
 * until the studio supplies its own images.
 */
class Catalog
{
    /**
     * Original public browsing categories. `image` names the Home category
     * photo and resolves against config/gossips.php.
     *
     * @return array<int, array{slug: string, name: string, summary: string, image: string}>
     */
    public static function categories(): array
    {
        return [
            [
                'slug' => 'nails',
                'name' => 'Nails',
                'summary' => 'Manicure, pedicure, hard gel, extensions and design.',
                'image' => 'nails',
            ],
            [
                'slug' => 'brows',
                'name' => 'Brows',
                'summary' => 'Lamination, shaping and tinting.',
                'image' => 'brushes',
            ],
            [
                'slug' => 'lashes',
                'name' => 'Lashes',
                'summary' => 'Lash lifts and tinting.',
                'image' => 'brows_lashes',
            ],
            [
                'slug' => 'hair',
                'name' => 'Hair',
                'summary' => 'Cuts and blow outs, colour, extensions and treatments.',
                'image' => 'hair',
            ],
            [
                'slug' => 'makeup',
                'name' => 'Makeup',
                'summary' => 'Application for events and occasions.',
                'image' => 'makeup',
            ],
        ];
    }

    /**
     * The original imported menu, in browsing order.
     *
     * `signature` marks the offerings the Home page leads with. It is a
     * display flag, not a separate record type.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        return [
            // Nails — manicure and gel.
            [
                'slug' => 'signature-hard-gel-overlay',
                'name' => 'Signature Hard Gel Overlay',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Russian manicure technique finished with a hard gel overlay.',
                'inclusions' => 'Nail art, removal and length changes are priced separately.',
                'duration_minutes' => 90,
                'price_mode' => 'from',
                'price_minor' => 9000,
                'price_note' => 'Short or medium CAD 90, long CAD 100, extra long CAD 110.',
                'signature' => true,
            ],
            [
                'slug' => 'shellac-manicure',
                'name' => 'Shellac Manicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Shaped and tidied nails finished in long-wear Shellac colour.',
                'inclusions' => 'Colour included; Shellac removal is a separate appointment.',
                'duration_minutes' => 60,
                'price_mode' => 'from',
                'price_minor' => 7500,
                'price_note' => null,
                'signature' => true,
            ],
            [
                'slug' => 'naked-manicure',
                'name' => 'Naked Manicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Cuticle work and a buffed natural finish, with no colour or overlay.',
                'inclusions' => 'No colour or overlay included.',
                'duration_minutes' => 30,
                'price_mode' => 'from',
                'price_minor' => 5500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'full-set-extensions',
                'name' => 'Full Set Extensions',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'A full set of extensions shaped and finished to your chosen length.',
                'inclusions' => 'Design and removal are priced separately.',
                'duration_minutes' => 120,
                'price_mode' => 'from',
                'price_minor' => 13000,
                'price_note' => null,
                'signature' => true,
            ],
            [
                'slug' => 'nail-extensions-refill',
                'name' => 'Nail Extensions Refill',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Rebalance and refill of an existing set of extensions.',
                'inclusions' => 'Design and removal are priced separately.',
                'duration_minutes' => 105,
                'price_mode' => 'from',
                'price_minor' => 11500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'manicure-for-men',
                'name' => 'Manicure for Men',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Cuticle work, shaping and a clean natural finish.',
                'inclusions' => 'No colour included.',
                'duration_minutes' => 40,
                'price_mode' => 'fixed',
                'price_minor' => 6000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'mini-me-manicure',
                'name' => 'The Mini Me Manicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'A short manicure with cuticle work and no colour.',
                'inclusions' => 'No colour or overlay included.',
                'duration_minutes' => 20,
                'price_mode' => 'fixed',
                'price_minor' => 4000,
                'price_note' => null,
                'signature' => false,
            ],

            // Nails — pedicure.
            [
                'slug' => 'signature-dry-pedicure',
                'name' => 'Signature Dry Pedicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Dry technique pedicure that keeps the nail plate dry throughout.',
                'inclusions' => '',
                'duration_minutes' => 90,
                'price_mode' => 'fixed',
                'price_minor' => 10500,
                'price_note' => null,
                'signature' => true,
            ],
            [
                'slug' => 'classic-pedicure',
                'name' => 'Classic Pedicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Foot care finished with regular polish.',
                'inclusions' => 'Regular polish included; gel colour is not.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 9000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'naked-pedicure',
                'name' => 'Naked Pedicure',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Cuticle and nail work on the feet, finished without polish.',
                'inclusions' => 'No polish included.',
                'duration_minutes' => 45,
                'price_mode' => 'fixed',
                'price_minor' => 8000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'pedicure-for-men',
                'name' => 'Pedicure for Men',
                'category' => 'Nails',
                'category_slug' => 'nails',
                'result' => 'Foot care, cuticle work and a clean finish.',
                'inclusions' => 'No polish included.',
                'duration_minutes' => 45,
                'price_mode' => 'fixed',
                'price_minor' => 9000,
                'price_note' => null,
                'signature' => false,
            ],

            // Brows.
            [
                'slug' => 'brow-complex',
                'name' => 'Brow Complex',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Brow lamination, shaping and tint in one appointment.',
                'inclusions' => 'Lamination, shape and tint included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 10500,
                'price_note' => null,
                'signature' => true,
            ],
            [
                'slug' => 'brow-lamination-and-shape',
                'name' => 'Brow Lamination + Shape',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Brow lamination with shaping, without a tint.',
                'inclusions' => 'No tint included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 7000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'brow-lamination-and-tint',
                'name' => 'Brow Lamination + Tint',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Brow lamination with a tint, without reshaping.',
                'inclusions' => 'No shaping included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 7500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'brow-shape-and-tint',
                'name' => 'Brow Shape + Tint',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Brows shaped and then tinted.',
                'inclusions' => 'Shape and tint included.',
                'duration_minutes' => 40,
                'price_mode' => 'fixed',
                'price_minor' => 6000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'brow-shape',
                'name' => 'Brow Shape',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Brows tidied and shaped.',
                'inclusions' => 'No tint included.',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 3500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'brow-tint',
                'name' => 'Brow Tint',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Tint applied to the brow hair.',
                'inclusions' => 'No shaping included.',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 3000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'facial-wax-one-area',
                'name' => 'Facial Wax (one area)',
                'category' => 'Brows',
                'category_slug' => 'brows',
                'result' => 'Waxing for one facial area.',
                'inclusions' => 'One area per appointment.',
                'duration_minutes' => 10,
                'price_mode' => 'fixed',
                'price_minor' => 1000,
                'price_note' => null,
                'signature' => false,
            ],

            // Lashes.
            [
                'slug' => 'lash-lift',
                'name' => 'Lash Lift',
                'category' => 'Lashes',
                'category_slug' => 'lashes',
                'result' => 'A lift that curls the natural lashes from the root.',
                'inclusions' => 'No tint included.',
                'duration_minutes' => 90,
                'price_mode' => 'fixed',
                'price_minor' => 8500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'lash-tint',
                'name' => 'Lash Tint',
                'category' => 'Lashes',
                'category_slug' => 'lashes',
                'result' => 'Tint applied to the natural lashes.',
                'inclusions' => 'No lift included.',
                'duration_minutes' => 30,
                'price_mode' => 'fixed',
                'price_minor' => 2500,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'brow-and-lash-duo',
                'name' => 'Brow & Lash Duo',
                'category' => 'Lashes',
                'category_slug' => 'lashes',
                'result' => 'Brow lamination with a lash lift in one appointment.',
                'inclusions' => 'Brow lamination and lash lift included; a brow tint is not.',
                'duration_minutes' => 120,
                'price_mode' => 'fixed',
                'price_minor' => 17500,
                'price_note' => null,
                'signature' => false,
            ],

            // Hair.
            [
                'slug' => 'womens-haircut',
                'name' => "Women's Haircut",
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A cut shaped to suit your hair and how you wear it.',
                'inclusions' => 'A blow dry finish is included.',
                'duration_minutes' => 60,
                'price_mode' => 'from',
                'price_minor' => 10000,
                'price_note' => 'Price varies with hair length and density.',
                'signature' => false,
            ],
            [
                'slug' => 'blow-out',
                'name' => 'Blow Out',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Hair washed and finished with a blow dry style.',
                'inclusions' => 'Wash and finish included.',
                'duration_minutes' => 30,
                'price_mode' => 'from',
                'price_minor' => 7000,
                'price_note' => 'Price varies with hair length and density.',
                'signature' => false,
            ],
            [
                'slug' => 'bangs-cut',
                'name' => 'Bangs Cut',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A trim for your fringe.',
                'inclusions' => 'Fringe only.',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 2000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'colour-transformation-consultation',
                'name' => 'Colour Transformation Consultation',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A consultation to plan a colour change and price it properly.',
                'inclusions' => 'No colour is applied in this appointment.',
                'duration_minutes' => 30,
                'price_mode' => 'free',
                'price_minor' => 0,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'full-colour',
                'name' => 'Full Colour',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'All-over colour from root to end.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 27000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'root-touch-up',
                'name' => 'Root Touch Up',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Colour applied to regrowth only.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 30,
                'price_mode' => 'fixed',
                'price_minor' => 24000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'root-white-out',
                'name' => 'Root White Out (1 inch)',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Whitening applied to one inch of regrowth.',
                'inclusions' => 'Regrowth only, up to one inch.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 33000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'face-frame-highlights',
                'name' => 'Face Frame Highlights',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Highlights placed around the face.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 120,
                'price_mode' => 'fixed',
                'price_minor' => 25000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'half-head-highlights',
                'name' => 'Half Head Highlights',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Highlights through the top half of the head.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 180,
                'price_mode' => 'from',
                'price_minor' => 30000,
                'price_note' => 'Price varies with hair length and density.',
                'signature' => false,
            ],
            [
                'slug' => 'full-head-highlights',
                'name' => 'Full Head Highlights',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Highlights placed throughout the head.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 210,
                'price_mode' => 'from',
                'price_minor' => 40000,
                'price_note' => 'Price varies with hair length and density.',
                'signature' => false,
            ],
            [
                'slug' => 'full-head-babylights',
                'name' => 'Full Head Babylights',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Fine, closely woven highlights throughout the head.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 210,
                'price_mode' => 'from',
                'price_minor' => 44000,
                'price_note' => 'Price varies with hair length and density.',
                'signature' => false,
            ],
            [
                'slug' => 'balayage',
                'name' => 'Balayage',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'Hand-painted lightening with a soft grow-out.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 120,
                'price_mode' => 'from',
                'price_minor' => 55000,
                'price_note' => 'Priced after a colour consultation.',
                'signature' => false,
            ],
            [
                'slug' => 'hair-extensions-consultation',
                'name' => 'Hair Extensions Consultation',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A consultation to plan extensions, colour matching and price.',
                'inclusions' => 'No extensions are applied in this appointment.',
                'duration_minutes' => 30,
                'price_mode' => 'free',
                'price_minor' => 0,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'hair-topper-consultation',
                'name' => 'Hair Topper Consultation',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A consultation about a hair topper, including colour matching.',
                'inclusions' => 'No topper is fitted in this appointment.',
                'duration_minutes' => 30,
                'price_mode' => 'free',
                'price_minor' => 0,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'hair-botox',
                'name' => 'Hair Botox',
                'category' => 'Hair',
                'category_slug' => 'hair',
                'result' => 'A smoothing treatment applied through the hair.',
                'inclusions' => 'A blow dry finish is not included.',
                'duration_minutes' => 240,
                'price_mode' => 'fixed',
                'price_minor' => 40000,
                'price_note' => null,
                'signature' => false,
            ],

            // Makeup.
            [
                'slug' => 'soft-glam-makeup',
                'name' => 'Soft Glam Makeup',
                'category' => 'Makeup',
                'category_slug' => 'makeup',
                'result' => 'A soft, wearable makeup application.',
                'inclusions' => 'Lashes are not included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 13000,
                'price_note' => null,
                'signature' => true,
            ],
            [
                'slug' => 'full-glam-makeup',
                'name' => 'Full Glam Makeup',
                'category' => 'Makeup',
                'category_slug' => 'makeup',
                'result' => 'A fuller makeup application for an event.',
                'inclusions' => 'Lashes are not included.',
                'duration_minutes' => 60,
                'price_mode' => 'fixed',
                'price_minor' => 16000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'makeup-and-blow-out',
                'name' => 'Makeup + Blow Out',
                'category' => 'Makeup',
                'category_slug' => 'makeup',
                'result' => 'Makeup application with a blow dry finish in one appointment.',
                'inclusions' => 'Makeup and blow out included.',
                'duration_minutes' => 120,
                'price_mode' => 'fixed',
                'price_minor' => 20000,
                'price_note' => null,
                'signature' => false,
            ],
            [
                'slug' => 'makeup-and-hair-style',
                'name' => 'Makeup + Hair Style',
                'category' => 'Makeup',
                'category_slug' => 'makeup',
                'result' => 'Makeup application with a styled finish in one appointment.',
                'inclusions' => 'Makeup and styling included.',
                'duration_minutes' => 150,
                'price_mode' => 'fixed',
                'price_minor' => 25000,
                'price_note' => null,
                'signature' => false,
            ],
        ];
    }

    /**
     * The signature offerings the Home page leads with (03 section 2). Nails
     * lead, followed by one brow and one makeup service, so the wider offer is
     * visible without inventing a separate pitch for it.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function signatureServices(): array
    {
        return array_values(array_filter(
            self::catalog(),
            static fn (array $service): bool => $service['signature'] === true
        ));
    }

    /**
     * Every service in one browsing category, in catalog order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function servicesInCategory(string $categorySlug): array
    {
        return array_values(array_filter(
            self::catalog(),
            static fn (array $service): bool => $service['category_slug'] === $categorySlug
        ));
    }

    /**
     * Nail removal and design (an add-on has its own
     * price, time and compatible service, and is never folded into a service
     * row). Only the salon's own published nail add-ons are listed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function addOns(): array
    {
        return [
            [
                'slug' => 'shellac-removal-only',
                'name' => 'Shellac removal only',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 2000,
                'note' => 'Cannot be combined with another service.',
            ],
            [
                'slug' => 'dip-acrylic-extension-removal',
                'name' => 'Dip powder, acrylic or extension removal',
                'duration_minutes' => 30,
                'price_mode' => 'fixed',
                'price_minor' => 3500,
                'note' => 'Removal only; a replacement set is booked as a separate service.',
            ],
            [
                'slug' => 'pedicure-gel-removal',
                'name' => 'Pedicure gel removal',
                'duration_minutes' => 10,
                'price_mode' => 'fixed',
                'price_minor' => 2000,
                'note' => null,
            ],
            [
                'slug' => 'advanced-nail-design',
                'name' => 'Advanced nail design',
                'duration_minutes' => 10,
                'price_mode' => 'from',
                'price_minor' => 4500,
                'note' => 'Charged per nail.',
            ],
            [
                'slug' => 'simple-design-1-4-nails',
                'name' => 'Simple design, 1–4 nails',
                'duration_minutes' => 5,
                'price_mode' => 'from',
                'price_minor' => 1000,
                'note' => null,
            ],
            [
                'slug' => 'simple-design-5-10-nails',
                'name' => 'Simple design, 5–10 nails',
                'duration_minutes' => 10,
                'price_mode' => 'from',
                'price_minor' => 2000,
                'note' => null,
            ],
            [
                'slug' => 'french-design',
                'name' => 'French design',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 2000,
                'note' => null,
            ],
            [
                'slug' => 'ombre-design',
                'name' => 'Ombré design',
                'duration_minutes' => 15,
                'price_mode' => 'fixed',
                'price_minor' => 3000,
                'note' => null,
            ],
            [
                'slug' => 'cat-eye-design',
                'name' => 'Cat eye design',
                'duration_minutes' => 5,
                'price_mode' => 'fixed',
                'price_minor' => 1000,
                'note' => null,
            ],
            [
                'slug' => 'chrome-powder-design',
                'name' => 'Chrome powder design',
                'duration_minutes' => 5,
                'price_mode' => 'fixed',
                'price_minor' => 1000,
                'note' => null,
            ],
            [
                'slug' => 'toe-wax',
                'name' => 'Toe wax',
                'duration_minutes' => 5,
                'price_mode' => 'fixed',
                'price_minor' => 1000,
                'note' => null,
            ],
        ];
    }

    /**
     * The add-ons that apply to a browsing category. The salon publishes its add-ons
     * under nails only, so every other category gets none rather than an invented set
     * (04: an add-on names the services it is compatible with).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function addOnsFor(?string $categorySlug): array
    {
        return $categorySlug === 'nails' ? self::addOns() : [];
    }

    /**
     * One service record by public slug, searched across the whole menu so a
     * service chosen on any page reaches the booking flow with its own details
     * (03, 05). Returns null for anything not in the catalog.
     *
     * @return array<string, mixed>|null
     */
    public static function findService(?string $slug): ?array
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        foreach (self::catalog() as $service) {
            if ($service['slug'] === $slug) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Filter the catalog by category slug and/or a free-text query. Both match
     * against the customer-facing fields only, so a search never surfaces a
     * record the visitor could not see.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(?string $categorySlug = null, ?string $query = null): array
    {
        $needle = $query === null ? '' : Str::lower(trim($query));

        return array_values(array_filter(
            self::catalog(),
            static function (array $service) use ($categorySlug, $needle): bool {
                if ($categorySlug !== null && $service['category_slug'] !== $categorySlug) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', [
                    $service['name'],
                    $service['category'],
                    $service['result'],
                    (string) $service['inclusions'],
                    (string) ($service['price_note'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            }
        ));
    }

    /**
     * Work tiles for the gallery (03 section 4).
     *
     * Client-supplied portfolio images are described by the visible finish only;
     * captions avoid assigning a named artist or promising a treatment outcome.
     *
     * @return array<int, array{slug: string, caption: string, category: string, category_slug: string}>
     */
    public static function workGallery(): array
    {
        return [
            ['slug' => 'nail-french-finish', 'caption' => 'Soft French finish', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-french'],
            ['slug' => 'nail-red-colour', 'caption' => 'Glossy red colour', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'polish'],
            ['slug' => 'nail-garden-detail', 'caption' => 'A fresh set outdoors', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'flat_lay'],
            ['slug' => 'nail-soft-finish', 'caption' => 'Soft neutral tones', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-soft'],
            ['slug' => 'nail-dark-finish', 'caption' => 'Deep glossy finish', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-dark'],
            ['slug' => 'nail-glitter-detail', 'caption' => 'Glitter accents', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'hand_care'],
            ['slug' => 'nail-colour-art', 'caption' => 'Colourful nail art', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-colour'],
            ['slug' => 'nail-blue-detail', 'caption' => 'Blue and pink detail', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-blue'],
            ['slug' => 'nail-neutral-finish', 'caption' => 'A natural finish', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'work-neutral'],
            ['slug' => 'nail-pedicure', 'caption' => 'Pedicure colour', 'category' => 'Nails', 'category_slug' => 'nails', 'image' => 'pedicure'],
        ];
    }

    /**
     * Filter the gallery for the Home preview (03 section 4: 4–8 images) or for a
     * category on the work page. Nails lead the catalog order, so the preview keeps
     * the studio's nail focus.
     *
     * `image` names a photograph in config/gossips.php; the view resolves it, so a
     * tile keeps its slot when the studio's own photography replaces a stock frame.
     *
     * @return array<int, array{slug: string, caption: string, category: string, category_slug: string, image: string}>
     */
    public static function workTiles(?string $categorySlug = null, ?int $limit = null): array
    {
        $tiles = array_filter(
            self::workGallery(),
            static fn (array $tile): bool => $categorySlug === null || $tile['category_slug'] === $categorySlug
        );

        $tiles = array_values($tiles);

        return $limit === null ? $tiles : array_slice($tiles, 0, $limit);
    }

    /**
     * Home page work preview captions (03 section 4). Six slots: nails lead and the
     * other categories are represented.
     *
     * @return array<int, string>
     */
    public static function workPreview(): array
    {
        return array_column(self::workTiles(null, 6), 'caption');
    }

    /**
     * The rating Gossips publishes on its own DIKIDI profile (03 section 6, 04
     * gallery and reviews). It is a platform-specific figure, not an average
     * across platforms, and it is shown with its source and the date it was read
     * so a visitor can check it rather than trust it.
     *
     * No review excerpt, customer name or star breakdown is reproduced here. Those
     * need the owner's permission and an allowed excerpt first; without them the
     * section stays smaller instead of being filled with invented testimonials.
     *
     * @return array{value: string, count: string, platform: string, url: string, observed: string}
     */
    public static function ratingSummary(): array
    {
        return [
            'value' => '5.0',
            'count' => '54 ratings',
            'platform' => 'DIKIDI',
            'url' => 'https://dikidi.net/674763',
            'observed' => '24 September 2026',
        ];
    }

    /**
     * Google publishes its own rating and review count for the studio. Keep the
     * figure separate from DIKIDI and link back to Google for the review text.
     *
     * @return array{value: string, count: string, platform: string, url: string, observed: string}
     */
    public static function googleRatingSummary(): array
    {
        return [
            'value' => '4.8',
            'count' => '149 reviews',
            'platform' => 'Google',
            'url' => 'https://maps.app.goo.gl/qv3gBPGZZBaayDwB6?g_st=iw',
            'observed' => '25 September 2026',
        ];
    }

    /**
     * Studio introduction (03 section 5). Only what is confirmed: the salon's own
     * story, history and specialties have to come from the owner and are not
     * written here.
     */
    public static function studioSummary(): string
    {
        return 'Gossips Beauty is a single-location studio on The Queensway in Etobicoke, offering nail care alongside brows, lashes, hair and makeup.';
    }

    /**
     * Visit details (03 section 7). The street address is confirmed in the brief;
     * opening hours and the phone number are not, so they are absent rather than
     * guessed.
     *
     * @return array<string, string>
     */
    public static function visit(): array
    {
        $location = config('gossips.location');

        return [
            'address' => sprintf(
                '%s, %s, %s',
                $location['address_line'],
                $location['city'],
                $location['region']
            ),
            'address_lines' => [
                $location['address_line'],
                sprintf('%s, %s', $location['city'], $location['region']),
            ],
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(
                $location['address_line'].', '.$location['city'].', '.$location['region']
            ),
        ];
    }

    /**
     * Format integer minor units as a CAD label, e.g. 9000 => "CAD 90".
     * Whole-dollar prices read cleaner without the ".00" tail, but any figure
     * with cents keeps them so a real value can never be rounded away.
     */
    public static function formatCad(?int $minorUnits): ?string
    {
        if ($minorUnits === null) {
            return null;
        }

        $units = intdiv($minorUnits, 100);
        $cents = abs($minorUnits % 100);

        return $cents === 0
            ? 'CAD '.number_format($units)
            : 'CAD '.number_format($units).'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The visible price basis for a card: fixed, "from", free or quoted at
     * booking. A "from" price is never presented as a fixed total, and a quote is
     * never presented as an amount (00, 04).
     *
     * @param  array<string, mixed>  $service
     */
    public static function priceLabel(array $service): string
    {
        if (($service['price_mode'] ?? null) === 'free') {
            return 'Free';
        }

        $amount = self::formatCad($service['price_minor'] ?? null);

        if ($amount === null) {
            return 'Quoted at booking';
        }

        return ($service['price_mode'] ?? null) === 'from' ? 'from '.$amount : $amount;
    }

    /**
     * Duration is only shown when it is a real configured number; otherwise the
     * card shows no time at all, because a scheduling estimate must never be
     * invented (04, 05).
     *
     * @param  array<string, mixed>  $service
     */
    public static function durationLabel(array $service): ?string
    {
        $minutes = $service['duration_minutes'] ?? null;

        if ($minutes === null) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' hr';
        }
        if ($rest > 0) {
            $parts[] = $rest.' min';
        }

        return implode(' ', $parts);
    }
}
