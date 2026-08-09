# Google Maps Embed API — Research Brief for Vishwaguru Contact Page

**Compiled:** 2026-08-09. **Target:** Laravel 10 + Inertia 2 + Svelte 5. **Trust coordinates:** 12.331205, 76.666993 (Mysore, India). Low-traffic shared hosting.

## Section 1 — Embed API URL pattern (working example + doc URL)

**URL pattern:** `https://www.google.com/maps/embed/v1/{MAP_MODE}?key=YOUR_API_KEY&{PARAMETERS}` placed in an iframe `src` (https://developers.google.com/maps/documentation/embed/get-started; https://developers.google.com/maps/documentation/embed/embedding-map). API key **required** (https://developers.google.com/maps/documentation/embed/usage-and-billing).

**Pin mode (with marker):** `?key=…&q=12.331205,76.666993&zoom=16` — `q` accepts lat/lon, place name, plus code, or Place ID (https://developers.google.com/maps/documentation/embed/embedding-map).

**Working example:** `https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY&q=12.331205,76.666993&zoom=16`

**Required iframe attribute:** `referrerpolicy="strict-origin-when-cross-origin"` — without it, "API key restriction [will] reject the requests" (https://developers.google.com/maps/documentation/embed/embedding-map).

## Section 2 — API key acquisition + 2026 pricing

**Setup** (https://developers.google.com/maps/documentation/embed/get-api-key): Google Cloud project → attach billing account → enable "Maps Embed API" → create API key.

**2026 pricing — FREE, unlimited:** "All Maps Embed API requests are available at no charge with unlimited usage" and "Maps Embed usage is available at no charge" (https://developers.google.com/maps/documentation/embed/get-started; https://developers.google.com/maps/documentation/embed/usage-and-billing). No QPS/daily quota either. Essentials SKU "Maps Embed" is the only billing code, priced at zero.

Billing account must still be attached for the API to enable, but no charges accrue for Embed calls. [unverified — docs don't state whether zero-usage email alerts fire]

## Section 3 — Security best practices for the key

**Google's own guidance** (https://developers.google.com/maps/api-security-best-practices): "Restrict your API keys to prevent unauthorized usage. You are financially responsible for charges caused by abuse of unrestricted API keys."

**The key MUST be client-side** (it goes in the iframe `src`). Google documents two mitigations:

1. **Application restriction → "Websites"** with referrer matching the trust's domain, e.g. `https://vishwaguru.example.com/*` and `https://www.vishwaguru.example.com/*`. Wildcards `https://*.example.com/*` authorize all subdomains (https://developers.google.com/maps/api-security-best-practices).
2. **API restriction → restrict to "Maps Embed API" only** so a leaked key can only call the free SKU.

**Proxying through Laravel is NOT applicable** — the iframe is fetched directly by the browser from `google.com`, not by your server. The server-proxy pattern only applies to server-side REST calls (Geocoding, Places), which is irrelevant here.

**Acceptable because** the Embed API is free with no quotas, so a leak causes at most a map on another site — not a billing blast.

## Section 4 — Coordinate input formats

- **Comma-separated lat,lon** in `q=`: `q=12.331205,76.666993` (https://developers.google.com/maps/documentation/embed/embedding-map).
- **Plus Codes:** `q=849VCWC8%2BR9`.
- **Place ID:** `q=place_id:ChIJ…` — supported for stable business IDs (https://developers.google.com/maps/documentation/embed/embedding-map).
- **No-marker mode (`view`):** `center=lat,lon` + `zoom=0–21`.

**Place ID from lat/lon:** Not available via the Embed API directly. Requires a Places API server-side call (out of scope for our use case). For a single fixed site, paste `https://www.google.com/maps/place/?q=PLACE_NAME` in a browser and inspect the redirected URL's `ftid`/`data=` parameter, or use the Place ID Finder tool (https://developers.google.com/maps/documentation/javascript/place-id).

**Verdict:** Use raw lat/lon — matches the codebase's existing canonical coordinates and is simpler.

## Section 5 — Laravel packages — no package needed

**No package needed.** The Embed API is one URL string in an iframe — PHP SDK adds nothing. `config('services.google.maps_key')` + string concat is sufficient.

**Evidence scan (Packagist + GitHub):**

| Package | Stars | Last commit | Verdict |
|---|---|---|---|
| `alexpechkarev/google-maps` | 568⭐ / 580 favers | Jun 19, 2026 (active) | Overkill — wraps Web Services (Geocoding, Places, Routes), not Embed. https://github.com/alexpechkarev/google-maps |
| `cornford/googlmapper` | 458⭐ / 465 favers | 4 years ago (Laravel 9) | Stale + overkill — wraps JavaScript API. https://github.com/bradcornford/Googlmapper |
| `cheesegrits/filament-google-maps` | 326 favers | Active | Wrong framework (Filament, not Svelte). |

**Confirmed NOT to exist on Packagist:** `spatie/laravel-google-maps` (Spatie only ships `spatie/google-time-zone`), `davidnagy/laravel-google-maps-search`, `slynchuk/laravel-google-api`, `anthonyedmonds/laravel-google-maps` — all 404.

## Section 6 — Operational risks of the current OSM implementation

1. **Tile Usage Policy violations** if the code is ever swapped from a sanctioned iframe to a Leaflet/MapLibre wrapper pulling `tile.openstreetmap.org` directly without a `User-Agent` header — OSMF "may withdraw access at any point" with no SLA (https://operations.osmfoundation.org/policies/tiles/).
2. **No reliability/SLA.** Volunteer-run, donation-funded; 503s documented in OSMF post-mortems. For a small temple site this means unexplained blanks are possible.
3. **Attribution compliance risk** — "Show OpenStreetMap licence attribution clearly on the map (typically bottom-right)" (https://operations.osmfoundation.org/policies/tiles/). Custom CSS on mobile can clip the attribution → legal grey area.
4. **Accuracy/coverage in India** — well-mapped in Mysore city, but gaps in lanes/rural sites. Google Maps is consistently better ground-truth.
5. **User trust** — the user already flagged OSM as not a trusted platform. Audience expects Google Maps brand recognition.
6. **No driving-directions link** — OSM embed can't link out to Google Maps navigation; the Embed API's `place` mode shows a panel that does.
7. **Privacy optics** — OSMF logs "summary data on tiles accessed and websites/apps using the service" (https://operations.osmfoundation.org/policies/tiles/). Google logs more, but that's the established expectation.

## Section 7 — Recommended integration approach

- **Sign up** for Google Cloud, attach a billing account (zero charges will result for the Embed API), enable "Maps Embed API" only, create a key with **Application → Websites** restriction matching the trust's domain (e.g. `https://vishwaguru.example.com/*`) **and API → "Maps Embed API"** restriction.
- **Add `GOOGLE_MAPS_KEY` to `.env`**, expose via `config/services.php`. No composer dependency. Pass the key to the Inertia `Contact` page shared props and build the URL in the Svelte component.
- **Render the iframe** with `loading="lazy"`, `referrerpolicy="strict-origin-when-cross-origin"` (required), `width="100%"`, `height="450"`, `style="border:0"`, `allowfullscreen`, and `src` = `https://www.google.com/maps/embed/v1/place?key=…&q=12.331205,76.666993&zoom=16`. Include a `title` attribute for accessibility.
- **Keep coords as a config constant** in `config/services.php` or a dedicated `config/temple.php`; `zoom=16` (~250 m radius) frames a temple site well.
- **Operational:** keep billing alerts on (even at $0) and review the credentials page quarterly. The Embed API is documented as free with unlimited usage in 2026 (https://developers.google.com/maps/documentation/embed/usage-and-billing), so this is defensible long-term; if Google ever changes pricing, the swap-out is one config change.
