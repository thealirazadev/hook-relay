# Design — hook-relay

The dashboard is a deliberately boring, server-rendered operations UI: one static stylesheet
(`public/css/app.css`), no CSS framework, no JavaScript build, no client-side state. Every screen
is a document: tables, forms, definition lists, and links. Density and scannability beat visual
flair — the primary user activity is scanning tables of events and deliveries for anomalies.

## Color & theme

Light theme only in v1. Neutral grays, one accent, and a fixed status palette.

| Token | Hex | Use |
|---|---|---|
| `--bg` | `#f6f7f9` | Page background |
| `--surface` | `#ffffff` | Cards, table backgrounds |
| `--border` | `#d9dde3` | Borders, table rules |
| `--text` | `#1f2933` | Body text (12.6:1 on surface) |
| `--text-muted` | `#57606a` | Secondary text, timestamps (6.4:1) |
| `--accent` | `#1d4ed8` | Links, primary buttons, focus rings |
| `--accent-hover` | `#1e40af` | Link/button hover |
| `--danger` | `#b91c1c` | Destructive buttons, error text |

Delivery status badges (background / text — all meet AA against their background; the label text
always accompanies the color, never color alone):

| Status | Background | Text |
|---|---|---|
| `pending` | `#e5e7eb` | `#374151` |
| `delivering` | `#dbeafe` | `#1e40af` |
| `delivered` | `#d1fae5` | `#065f46` |
| `failed` | `#fef3c7` | `#92400e` |
| `dead` | `#fee2e2` | `#991b1b` |

## Fonts & typography scale

- UI: the system font stack (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`). No
  webfonts — zero requests, native rendering.
- Payloads, headers, ids, URLs, response excerpts: `ui-monospace, SFMono-Regular, Menlo, Consolas,
  monospace`.
- Scale (rem): page title 1.5 / 600; section heading 1.125 / 600; body and table cells 0.9375 /
  400; small (timestamps, badges, table headers) 0.8125 / 500 with sentence case (no all-caps
  below 14px without extra letter-spacing); monospace blocks 0.8125. Line height 1.5 body, 1.3
  headings.

## Spacing, radius, shadows

- Spacing on a 4/8px system: 4, 8, 12, 16, 24, 32, 48. Table cell padding 8×12; card padding 16;
  page gutter 24; section gap 32. Max content width 1200px, centered.
- Border radius: 6px cards/inputs/buttons, 4px badges/code blocks. Nothing pill-shaped except
  status badges (9999px).
- Shadows: cards `0 1px 2px rgb(0 0 0 / 0.05)` plus the 1px border; no other shadows. Elevation
  is not a metaphor this product needs.

## Components & states

Every interactive element defines all of: default, hover, focus, disabled. Screens define error,
loading, and empty.

- **Buttons** — Primary (accent bg, white text), secondary (surface bg, border, text), danger
  (danger bg, white text; used for delete and requeue-all). Hover darkens bg; focus shows the
  global focus ring; disabled: 50% opacity, `cursor: not-allowed`, and the `disabled` attribute.
  Destructive actions confirm via an inline confirm form, not JS `confirm()`.
- **Links** — Accent color, underlined on hover and focus. Row-level primary actions (view event,
  view delivery) are real links, so middle-click works.
- **Forms** — Every input has a visible `<label>`; help text under the input in muted text.
  Error state: 1px danger border plus a danger-colored message under the field (never color
  alone); values are repopulated on validation failure. Secrets use `type="password"` with no
  reveal after save.
- **Tables** — Header row in small type; zebra striping off (rules only); numeric and timestamp
  columns right-aligned; monospace for ids and URLs with `text-overflow: ellipsis` and a `title`
  attribute for the full value. Wide tables scroll horizontally inside the card, never the page.
- **Status badges** — Colored pill + text label per the table above; identical everywhere a
  status appears.
- **Flash messages** — One region under the header: success (delivered-green scheme) and error
  (dead-red scheme), plain text, dismissed by navigation.
- **Loading** — Pages are server-rendered, so loading states are the browser's own. Form posts
  that dispatch work (replay, requeue-all) return immediately with a flash like
  "Requeued 12 deliveries" — the queue does the work; the UI never spins.
- **Empty states** — Every index renders a one-line explanation plus the obvious next action
  ("No sources yet. Create your first source.") — never a bare empty table. Filtered-to-empty
  states say so and offer a "Clear filters" link.
- **Payload viewer** — `<pre>` block, monospace, max height with vertical scroll, always
  Blade-escaped (payloads are attacker-controlled; see `docs/rules.md`).

## Accessibility baseline

- Semantic HTML: one `<h1>` per page, `<nav>`/`<main>` landmarks, real `<table>` with `<th
  scope="col">`, real `<button>`/`<a>` (no clickable divs), `<html lang="en">`.
- Every form input has a programmatically associated `<label>`; grouped checkboxes (destination
  routing) sit in a `<fieldset>` with a `<legend>`.
- Fully keyboard-navigable: logical tab order, no traps, no keyboard-only dead ends; all
  functionality works without JavaScript because there effectively is none.
- Visible focus: a global 2px accent outline with 2px offset on every interactive element; never
  `outline: none` without a replacement.
- Contrast: all text meets WCAG AA (4.5:1; large headings 3:1); status is always conveyed by
  text plus color.
- Flash and validation messages are plain text in the document flow, present on page load —
  no live-region timing issues.
- Viewport meta set; layout readable at 320px width (tables scroll, forms stack).

## Error pages

Framework 404 and 419 (expired session/CSRF) pages are replaced with minimal branded Blade pages
using the same layout: `<h1>` naming the state, one sentence, a link back to `/`. The ingest
endpoint never renders HTML — JSON envelope only, per `docs/api-contracts.md`.
