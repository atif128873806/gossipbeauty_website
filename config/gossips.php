<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio display facts
    |--------------------------------------------------------------------------
    |
    | Non-secret facts about the single Toronto location. Times and dates are
    | always stored as unambiguous UTC instants and converted for display with
    | this timezone; never store local times or hardcode the timezone inside a
    | controller or view.
    |
    | Booking and scheduling invariants are summarized in AGENTS.md.
    |
    */

    'timezone' => 'America/Toronto',

    'currency' => 'CAD',

    'location' => [
        'address_line' => '649 The Queensway',
        'city' => 'Etobicoke',
        'region' => 'Toronto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking flow
    |--------------------------------------------------------------------------
    |
    | `enabled` is a legacy booking flag; the live online switch and editable
    | staff schedule are database-backed. `schedule` remains for the legacy
    | preview fixture. The owner approved these starter hours for live booking,
    | and the studio still needs to review its actual shifts and breaks.
    |
    | `days` are weekday numbers, 0 = Sunday, so 2-6 is Tuesday to Saturday.
    | `slot_minutes` is the increment between offered start times. `days_ahead`
    | bounds how far ahead the flow looks for open days.
    |
    */

    'booking' => [
        'enabled' => true,
        'email_enabled' => (bool) env('BOOKING_EMAIL_ENABLED', false),

        'schedule' => [
            'days' => [2, 3, 4, 5, 6],
            'opens_at' => '10:00',
            'closes_at' => '19:00',
            'slot_minutes' => 30,
            'days_ahead' => 14,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Studio panel preview
    |--------------------------------------------------------------------------
    |
    | A legacy illustrative panel route remains separate from the live,
    | authenticated /admin panel. Set `preview` to false to remove the route.
    |
    */

    'studio_panel' => [
        'preview' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Photography
    |--------------------------------------------------------------------------
    |
    | Every image the site renders, with its alt text and dimensions, so a view
    | never hardcodes a path and swapping a photograph cannot change a layout.
    | Replace `file` with the studio's own photograph as Atif supplies them and
    | keep the same key, aspect ratio and alt contract.
    |
    | The photography mixes licensed stock with the salon photo files supplied
    | for this site. Dimensions stay explicit so the image frames remain stable.
    | Category illustrations without a matching salon photo continue to use stock.
    |
    */

    'photography' => [

        'hero' => [
            'file' => 'images/client/gossips-studio-hero.webp',
            'alt' => 'The Gossips Beauty salon interior and reception area.',
            'width' => 435,
            'height' => 290,
        ],

        'nails' => [
            'file' => 'images/client/gossips-studio-workroom.webp',
            'alt' => 'A manicure station with chairs and nail products at Gossips Beauty.',
            'width' => 421,
            'height' => 508,
        ],

        'brows_lashes' => [
            'file' => 'images/brows-lashes.webp',
            'alt' => 'Close view of the eyes, with the brows and lashes in focus.',
            'width' => 1000,
            'height' => 750,
        ],

        'hair' => [
            'file' => 'images/hair.webp',
            'alt' => 'A stylist trimming a section of hair with scissors.',
            'width' => 1000,
            'height' => 750,
        ],

        'makeup' => [
            'file' => 'images/makeup.webp',
            'alt' => 'An open eyeshadow palette with rows of colour pans.',
            'width' => 1000,
            'height' => 750,
        ],

        'studio' => [
            'file' => 'images/client/gossips-studio-lounge.webp',
            'alt' => 'A seating area inside Gossips Beauty.',
            'width' => 366,
            'height' => 406,
        ],

        'studio-chair' => [
            'file' => 'images/client/gossips-studio-chair.webp',
            'alt' => 'Salon chairs inside Gossips Beauty.',
            'width' => 451,
            'height' => 677,
        ],

        'flat_lay' => [
            'file' => 'images/client/gossips-nails-garden.webp',
            'alt' => 'A finished manicure photographed outdoors.',
            'width' => 381,
            'height' => 677,
        ],

        'polish' => [
            'file' => 'images/client/gossips-nails-red.webp',
            'alt' => 'A glossy red manicure.',
            'width' => 381,
            'height' => 508,
        ],

        'pedicure' => [
            'file' => 'images/client/gossips-pedicure.webp',
            'alt' => 'A close view of a finished pedicure.',
            'width' => 202,
            'height' => 270,
        ],

        'hand_care' => [
            'file' => 'images/client/gossips-nails-glitter.webp',
            'alt' => 'A manicure with glitter accents.',
            'width' => 508,
            'height' => 677,
        ],

        'brushes' => [
            'file' => 'images/brushes.webp',
            'alt' => 'A set of makeup brushes standing in a holder.',
            'width' => 800,
            'height' => 800,
        ],

        'tools' => [
            'file' => 'images/tools.webp',
            'alt' => 'A manicure tool set arranged in its case.',
            'width' => 800,
            'height' => 800,
        ],

        'work-french' => [
            'file' => 'images/client/gossips-nails-french.webp',
            'alt' => 'A soft French manicure.',
            'width' => 406,
            'height' => 406,
        ],

        'work-soft' => [
            'file' => 'images/client/gossips-nails-soft.webp',
            'alt' => 'A light neutral manicure.',
            'width' => 304,
            'height' => 406,
        ],

        'work-dark' => [
            'file' => 'images/client/gossips-nails-dark.webp',
            'alt' => 'A deep, glossy manicure photographed against greenery.',
            'width' => 508,
            'height' => 677,
        ],

        'work-colour' => [
            'file' => 'images/client/gossips-nails-colour.webp',
            'alt' => 'A colourful manicure with decorative accents.',
            'width' => 508,
            'height' => 677,
        ],

        'work-blue' => [
            'file' => 'images/client/gossips-nails-blue.webp',
            'alt' => 'A manicure with blue and pink nail art.',
            'width' => 304,
            'height' => 406,
        ],

        'work-neutral' => [
            'file' => 'images/client/gossips-nails-neutral.webp',
            'alt' => 'A close view of a neutral manicure.',
            'width' => 304,
            'height' => 406,
        ],
    ],

];
