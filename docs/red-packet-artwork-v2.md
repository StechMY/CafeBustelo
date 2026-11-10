# Red packet visual redesign

Built-in image generation was used (no CLI/API fallback). Final project assets:

- `public/images/red-packet/envelope-states-v2.png`: 1774 × 887 RGBA, aligned closed/open frames. Used by the interactive envelope, the falling envelopes and history decoration.
- `public/images/red-packet/prize-atlas-v2.png`: 1536 × 1024 RGBA, 3 × 2 reward atlas. Used in prize cards, the result dialog and the reward ledger. Images are illustrative; configured prize names and values remain authoritative.

## Design

Lacquer ruby `#ad1536`, deep red `#4a1025`, embossed gold `#edc27d`, blush paper `#fff8f2`, plum text `#482331`. Existing Golden Serif display type pairs with the site's interface font. A centred physical envelope is the focus; useful chance counts sit beside the action. The reward ledger uses a contrasting red header and paper list with two accessible tabs. Mobile prize cards use two columns with large artwork. The earlier thin decorative icons and unavailable-prize captions are removed. Stock/weight eligibility is unchanged.

GSAP sequences anticipation, closed/open artwork crossfade, a short shower of envelope images, then the result reveal. Only transforms and opacity animate. No persistent animation loops or reduced-motion branch. Request idempotency, retry recovery and in-flight guards remain intact. The two history paginators preserve one another's page and select their own tab via URL fragments.

## Generation prompts

### Envelope states

Create a polished production sprite sheet for a premium Vietnamese red-envelope rain event website, with ACTUAL transparent background. Canvas horizontal 2:1 aspect, exactly TWO equal square cells side by side with wide transparent gutters, no dividing lines. LEFT CELL: one large CLOSED upright red lucky-money envelope, front view with slight depth, rich scarlet lacquer paper, embossed flowing lotus gold foil corners, elegant circular brushed gold seal embossed with a simple four-petal flower (no letters). Refined luxury 3D product render, soft studio light, subtle gold edge highlights, crisp silhouette. RIGHT CELL: the EXACT SAME envelope in its OPEN state, identical envelope body size, front view and bottom alignment, upper flap lifted and a luminous warm-gold blank reward card emerging from the pocket, gentle golden light inside. Both bodies occupy the middle 65% width of their own square, bottoms at 86% canvas height; enough top room for the raised flap in right cell. Material-rich, premium game event art, realistic tactile paper and sculpted metal detail, not a line icon, not a flat vector, not cartoon clipart. No words, no numerals, no letters, no watermark, no background scene, no ground plane. Entire objects within cells. Main palette deep ruby #bd1335, bright red #ed3650, warm gold #eebe6b. This is an animation asset sprite sheet, not a UI mockup.

### Prize atlas

Create a production reward-icon atlas for a premium red-envelope event, ACTUAL transparent background, landscape 3:2 canvas with EXACTLY 3 equal columns and 2 equal rows, six square cells. No visible grid or frames. Each cell contains one separate large centered 3D sculpted product illustration, fully inside its cell with generous consistent 18 percent margin. Same soft studio lighting and camera angle across all six. TOP LEFT: elegant pearl white modern smartphone seen from back at slight angle, triple camera, no brand/logo. TOP CENTER: premium pearl white front loading washing machine with silver door. TOP RIGHT: pearl wireless earbuds and open charging case. BOTTOM LEFT: tidy bundle of green banknotes with a small champagne-gold ribbon, abstract fine line bill patterns, absolutely NO numerals or readable text. BOTTOM CENTER: dimensional metallic gold discount ticket with small embossed percent symbol only. BOTTOM RIGHT: small ruby red gift box with luxurious champagne gold bow. Rich clean materials, warm ivory highlights, champagne gold accents #eebe6b, ruby red #bd1335 accents, precise silhouettes, tasteful high-end mobile game event art, not thin line icons, not flat vector. No text except the percent symbol, no logos, no people, no scenery, no background, no detached sparkles outside cells. Equal visual scale and accurate tile centers so it can be used with CSS background-position.

### Final prize atlas edit

Edit target: the supplied six-object reward atlas. REMOVE THE ENTIRE BACKGROUND including all gray/beige/colored backdrop, haze, glow and ground shadows. Keep only the six isolated opaque physical objects on genuine transparent alpha: phone, washing machine, earbuds with case, money bundle, gold percent ticket, red gift box. Preserve each object's EXACT position, scale, shape, lighting, color and 3 by 2 layout; do not move or resize the objects. Preserve the original 1536x1024 canvas aspect. All space between objects and around their silhouettes must be completely transparent, not gray or checkerboard baked in. This is a clean production cutout sprite atlas. No other changes.
