# Redesign safety guarantees

- Redesigns reuse the existing `hub_design` ID.
- Existing `CONTENT.*` values remain stored outside visual versions.
- The current live version is not replaced until publish succeeds.
- A Page already rendered by HUB keeps serving the previous live version while a new draft is reviewed.
- A Theme/Elementor Page never switches to HUB without explicit activation and successful publication.
- `content_schema` type changes and new required fields without defaults force manual review.
- Oversized compressed HUB ZIP uploads are rejected before storage/extraction.
