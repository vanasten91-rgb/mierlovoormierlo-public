# Full-site audit theme integration

Release unit: `feature/full-site-audit-20260827`.

The live/draft child-theme `functions.php` must load the audit event/portal engine with:

```php
/** MvM Evenementen 2.0 + gescheiden organisator/ondernemersportalen. */
require_once get_stylesheet_directory() . '/mvm-events-2.php';
```

This marker is present in the WPVibe draft used for the audit. The repository deliberately tracks the audit-owned release files first rather than pretending the entire historical 99-file child theme was reconstructed from GitHub.

Audit-owned release files in this unit:

- `mvm-events-2.php`
- `mvm-events-2.css`
- `page-evenementen.php`
- `single-event_listing.php`
- `mvm-organisatoren.php`
- `mvm-ondernemers.php`
- existing `mvm-seo-v2.php`

Required behavior:

- staff roles win over organizer/entrepreneur auxiliary roles for login/admin routing;
- organizer-only accounts route to `/organisatoren/`;
- entrepreneur-only accounts route to `/ondernemers/`;
- legacy WP Event Manager submission/dashboard routes redirect canonically;
- portal routes are `noindex`, `nofollow`, `noarchive`;
- event detail uses the official `single_event_listing_button_start` hook for PeepSo/WPEM RSVP;
- event detail renders exactly one MvM share block and retains `comments_template()`;
- responsive and dark-mode rules live in `mvm-events-2.css`.
