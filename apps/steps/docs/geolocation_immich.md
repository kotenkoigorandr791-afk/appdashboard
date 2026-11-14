# Immich integration

Steps talks to Immich only through its HTTP API. The server URL and API key are encrypted
with the application's protected secret file (`STEPS_GEOLOCATION_KEY_FILE`, default
`/opt/AppDashboard/data/secrets/steps-geolocation.key`) and only ciphertext is stored in
`geo_immich_connections`.

## Sync behaviour

- A sync covers an explicit time window and fetches at most 20 pages of 100 metadata records.
- Assets are matched with bounded `ST_DWithin` + time-tolerance queries. The tolerance defaults
  are 250 m and 300 s and can be set in the connection options or with
  `GEO_IMMICH_DISTANCE_TOLERANCE_M` / `GEO_IMMICH_TIME_TOLERANCE_S`.
- Only asset ids, capture time, coordinates, match provenance and original filenames are stored.
  Image binaries stay in Immich.
- Thumbnails are fetched on demand through the authenticated Steps endpoint; the API key is never
  sent to the browser. Owners can access only their own assets, and family viewers never receive
  photo URLs.
- Missing-GPS photos get a time-based candidate. Writing GPS back to Immich requires the explicit
  confirmation flag and uses `PUT /api/assets/{id}`.
- Disconnect clears the encrypted credentials and deletes local asset references and trip-photo
  links. It never deletes or modifies anything in Immich.
- Deleted/unavailable assets returned as 404/410 are marked unavailable locally and their thumbnails
  stop rendering; a later sync also marks assets missing from a completed window unavailable.
