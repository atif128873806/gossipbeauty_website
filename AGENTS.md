# Gossips Beauty — agent handoff

Last updated: 28 September 2026. This is the single project handoff for coding agents.

## How to work here

- Read this file at the start of a task. **Do not follow a documentation-reading sequence or look for the deleted handoff documents.** Inspect the relevant source, configuration, migrations, routes and recent user instructions directly. Consult official framework documentation only when a task needs version-specific behavior.
- Atif's latest direct instruction takes precedence over this file. State the affected part of the product before changing it, then complete the work and report what changed, how it was checked and any real limitation.
- After a meaningful change, update the current state and next step in this file. Keep it short and accurate enough for the next agent to resume without conversation history. Never put passwords, private keys, customer contact data or production secrets here.
- The project is a live client application. Treat production data and public claims accordingly. Do not run the starter seeder against the live database or overwrite live appointments.

## Product and current state

Gossips Beauty is a beauty studio at **649 The Queensway, Etobicoke, Toronto**. This Laravel application owns its public website, customer booking journey and authenticated studio panel. DIKIDI was the prior booking source; the finished customer flow stays on this site. Atif stated there were no future DIKIDI reservations at launch. If later reservations are found, enter them through the panel before offering those times online.

The live site is [blue-pheasant-418401.hostingersite.com](https://blue-pheasant-418401.hostingersite.com). **Real public booking is open.** Atif approved a single studio, small-studio traffic, instant confirmation, a named eligible specialist or “Any available,” staff-managed cancellations/rescheduling, and the editable starter schedule of Tuesday–Saturday, 10:00–19:00. The seeded catalog currently has 40 timed services and seven staff profiles; Masha's existing new-client restriction remains. The studio must urgently review prices, durations, eligibility, shifts, breaks and leave because these starter values control real customer bookings.

Booking persists and confirms server-side. The public success page shows the saved reference. Confirmation email is disabled with `BOOKING_EMAIL_ENABLED=false` until a real SMTP sender is configured and delivery verified; the log mailer is not customer delivery. No payment or deposit is collected. A known live confirmed booking, reference `GB-2609-XHSJ7N` for 26 September 2026 at 13:00 Toronto time, was visible in both the hosted database and admin booking lists during the last check. Do not expose its customer's private information.

The private panel has separate Bookings, Customers, Services & extras, Team & hours, Time off & closures, Accounts and Settings sections. Bookings has Upcoming and All bookings, exact reference lookup, a day view, manual booking, appointment detail and status actions. Catalog, staff eligibility, weekly hours, exceptions and booking rules are editable through authorized panel controls. The owner account is `admin@gossipsbeauty.example`; its temporary password is intentionally undocumented and must be rotated by the studio. Replace the `.example` email when the studio supplies its address. The public mobile menu icon was fixed and deployed on 25 September 2026.

The public site currently includes Home, Services, Work and Booking. About, Visit, Policies and detailed service pages remain follow-up work pending verified content. Do not invent business facts to fill them.

## Stack and code landmarks

- Laravel 13, Blade, Livewire 4, Tailwind CSS 4 and Vite 8. The host runs PHP 8.4 with `pdo_sqlite`; local PHP is newer. Use the installed lockfiles as the version source.
- The database owns the live service catalog, staff, schedules, customers and appointments. `BookingCatalogSeeder` defines the starter import only. `app/Content/Catalog.php` retains some presentation and migration metadata; it is not the live booking catalog. `app/Content/StudioSchedule.php` and `app/Content/PanelDay.php` are legacy preview fixtures, not live availability.
- Photography is in `public/images/`, mapped with alt text and dimensions by `config/gossips.php`. Keep approved keys, crops and alt-text contracts when replacing an image.
- On 28 September the client approved the layouts, admin and booking process, and requested an Apple-inspired white-and-black palette with new typography. The shared theme now uses white/soft-gray surfaces, near-black text and actions, and self-hosted DM Sans variable fonts. Approved photography and layouts remain. Preserve keyboard access, readable labels, mobile navigation and reduced-motion behavior.
- **Theme changes belong in `resources/css/theme.css`.** It holds the palette, body/display font roles, shadows, focus and photograph contrast pairs. `resources/css/app.css` maps these roles to Tailwind utilities and shared components; do not introduce fixed colors in views or component CSS. Change the theme settings, run `npm run build`, then deploy the new built assets and any changed source. Dark photograph overlays intentionally use their own foreground/background pair.

## Booking and content invariants

- Public booking and admin actions use **one canonical catalog, availability service and booking writer**. Handle eligible staff, service/add-on time, weekly shifts, breaks, time off, buffers and any constrained resources. Recheck availability in the final write transaction so simultaneous requests cannot claim the same slot.
- Show times in `America/Toronto` and store unambiguous instants. Preserve appointment service, duration and price snapshots and audit changes. Schedule edits must not silently cancel existing appointments.
- Require authentication and server-side authorization for admin actions. Protect customer contact data; keep it out of URLs and logs. The app encrypts contact fields and uses keyed lookup hashes.
- Distinguish fixed, “from” and consultation-quoted prices. Never present a “from” price as a final total. Do not claim a booking, delivered email or payment before it actually occurs.
- Public pages and panel copy must read as a finished product: no “demo,” “sample,” “placeholder,” “concept” or “coming soon” labels. Omit unverified facts or ask Atif. Do not fabricate a logo, awards, certifications, staff biographies, reviews, ratings or results. Do not reuse private DIKIDI content without authorization.
- Avoid generic salon templates, stock-model hero clichés, floating gradient decoration, sparkle/robot icons and fake dashboard charts. Keep service names, prices and the booking action easy to read and navigate.
- Decisions that materially affect price, availability, communication, policy, legal wording, access, photos or migration require owner input if not already settled in this file or the current code. Routine implementation choices can be made from the existing conventions.

## Production operations

Hostinger application root: `/home/u764517528/apps/gossips-beauty-preview`. Web root: `/home/u764517528/domains/blue-pheasant-418401.hostingersite.com/public_html`. SSH account: `u764517528@82.29.157.119`, port `65002`; use key-based authentication and `ssh -F /dev/null` if local SSH config interferes. Keep credentials out of commands, output and this file.

The approved small-studio production database is SQLite with `DB_TRANSACTION_MODE=IMMEDIATE` and `DB_BUSY_TIMEOUT=10000`. The application still rechecks availability in the transaction. A hosted two-process same-slot probe on a disposable database copy produced one confirmation and one conflict. Move database engines only if traffic or hosting requirements justify it. HTTPS, `APP_DEBUG=false` and secure session cookies are enabled.

For deployment, first inspect the live state and take a **consistent SQLite `.backup`** plus a code/assets backup outside webroot; check `PRAGMA integrity_check` and restrict backup permissions. Existing snapshots are under the app's `backups/` directory. Preserve production `.env`, `APP_KEY`, `database/database.sqlite`, `vendor/`, and the Hostinger-specific `public_html/index.php`. Do not copy local `bootstrap/cache/*.php`: an earlier release accidentally brought development service cache to production. Deploy selected code, views and built assets; copy `public/build` into webroot. Apply new migrations with `php artisan migrate --force`, never rerun the starter seeder on live data, then run `php artisan optimize:clear` and `php artisan optimize`. Verify public routes and authorized panel views without creating fake customer appointments. Roll back code/assets if needed, but never restore an older database over newer bookings without reconciling every intervening appointment.

Customer email remains off until SMTP and, if needed, a supervised queue worker are configured and real delivery is checked. The SSH account has no `crontab`; arrange recurring SQLite snapshots through Hostinger hPanel or another supervised scheduler. The owner can pause online booking from the panel while correcting live schedule or catalog values.

## Next steps and open decisions

Next visual step: the client reviews the deployed white-and-black theme. Further palette or font changes should use the shared theme file.

1. Have the studio review and correct live service prices/durations, staff eligibility, shifts, breaks, leave and booking rules. Confirm the owner can find bookings in Upcoming and All bookings, then rotate the temporary password and replace the `.example` email.
2. Set up recurring consistent database backups and periodically verify a restore. Keep snapshots outside `public_html`.
3. Add SMTP only if the studio wants emailed confirmations. Verify actual delivery before changing `BOOKING_EMAIL_ENABLED`.
4. Complete remaining public pages and content management after the studio provides confirmed phone/contact details, policies, authorized photos/reviews and brand facts. Cancellation cutoff, deposits and other policy wording remain undecided; staff currently handle customer changes.

The old handoff documents were consolidated into this file and removed at Atif's request; future tasks must not recreate a mandatory document-reading workflow. **The monochrome theme was deployed on 28 September 2026.** Local Blade compilation and the Vite build passed; all seven admin sections rendered in read-only owner contexts locally and on the host with the new assets. Desktop/mobile review confirmed DM Sans loaded, white surfaces and dark text, and corrected the narrow-header fit issue. The hosted menu fits at 320px and 390px and opens/closes with centered icons. Home, Services, Work, Booking, admin sign-in, health and the font asset return HTTP 200; shared theme roles have no unresolved references. Primary text contrast on white is 16.83:1, secondary text 6.17:1, and field boundaries 3.68:1. The hosted appointment count remained one and online booking remained enabled. Host backups: `backups/monochrome-before-20260927T205600Z.sqlite` (integrity `ok`) and `backups/monochrome-code-before-20260927T205600Z.tar.gz`. No application tests were added or run for this theme change. Earlier booking-launch verification passed 105 tests / 1420 assertions and the hosted same-slot collision probe; those are historical results, not a test run of this visual release.
