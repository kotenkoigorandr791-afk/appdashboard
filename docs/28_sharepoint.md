# SharePoint — Universal Sharing System

**Location:** `project/sharepoint/`

The legacy `share_file_utils/` viewer has been removed. Public links now use a
single router and a universal `shares` table shared by every app.

## Public links

```
https://domain.com/s/<token>                     Main share page
https://domain.com/s/<token>/unlock              Password unlock (POST)
https://domain.com/s/<token>/content             Raw content / app JSON
https://domain.com/s/<token>/download[/<path>]   File download
https://domain.com/s/<token>/actions             App interactions (POST)
https://domain.com/s/admin                       Admin dashboard (role >= 2)
```

Old `?sharedFile=<token>` links redirect to `/s/<token>`.

## Resource types

| Type | App | Renderer |
|------|-----|----------|
| `file` | cloud | Image, video, audio, PDF, text, OnlyOffice, archive/fallback |
| `folder` | cloud | Breadcrumbs, listing, per-file preview and download |
| `app` / `recipe` | recipes | Interactive ingredients, steps, timers, progress |
| `app` / `expense_chart` | spends | Interactive bar/line/pie chart, periods, CSV |
| `app` / `step_routine` | steps | Daily steps chart and table |
| `app` / `film` | films | Poster, metadata, trailer |
| `app` / `smart_home_scene` | smart_home | Device states, read-only |

Apps create links through `POST /apps/<app>/api/share.php`. The request body is
the standard share contract (`resource_type`, `resource_id`, `resource_data`,
`permissions`, `expires_option`, `password`, `max_views`, `data_mode`).
The default expiration is **1 month**.

## Data and security

- Tokens: 32 random bytes encoded as URL-safe base64; SHA-256 hashes are stored
  for lookup. Legacy rows keep `token = share_id` and get hashed lazily.
- Permissions: `view`, `download`, `interact`, `edit`, `share`.
- Protection: expiration, revocation, max views, bcrypt/argon password hashes,
  five unlock attempts per hour per IP, folder path-traversal checks.
- Snapshot mode stores app data in `shares.snapshot_data`; otherwise the page
  reads live data on each request.
- `shared_files` is kept temporarily as a legacy mirror/fallback and is copied
  into `shares` during schema initialization.

## Design system

All public pages and the admin dashboard use Material Design 3 tokens and
components:

```
project/sharepoint/assets/css/md3-tokens.css
project/sharepoint/assets/css/md3-components.css
project/sharepoint/assets/css/md3-layout.css
project/sharepoint/assets/css/sharepoint.css
project/sharepoint/assets/js/md3-components.js
project/sharepoint/assets/js/sharepoint.js
project/sharepoint/assets/js/share-modal.js
project/sharepoint/assets/js/qr-code.js
```

The theme follows the system preference and can be switched from the top app
bar. The universal share modal is available as `window.openShareModal(...)` and
`window.SharepointShareModal`.
