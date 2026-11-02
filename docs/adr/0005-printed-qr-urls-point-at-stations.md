# Printed QR URLs point at Stations, on the main domain, by immutable short code

Gyms sticker their equipment with QR codes, and a printed sticker cannot be cheaply replaced, so everything in its URL must survive renames, re-seeds, domain changes and catalogue restructuring. We decided the only thing a sticker ever points at is a Station (a partner-owned, curated spot on the gym floor — see GLOSSARY) via `https://<main web app domain>/s/{short code}`, where the short code is generated once and never regenerated. The partner is derived from the Station server-side rather than carried in the URL.

## Considered Options

- **QR per exercise**: multiple stickers on multi-exercise equipment (a cable station supports dozens), and exercise identity (name or id) is mutable.
- **QR per EquipmentType**: the taxonomy is too coarse — a leg press and a chest press are both "Machine".
- **Partner subdomain in the printed URL** (`citygym.…/s/{code}`): reuses the existing branding-by-subdomain stack but couples printed plastic to partner domain configuration.

## Consequences

- Exercise pages under a Station are ordinary, unprinted links, so exercises need no public slug or short code.
- Station short codes must never be regenerated, recycled after deletion, or exposed to bulk-destroy; a deleted Station's URL must degrade to a graceful fallback page, never a 404.
