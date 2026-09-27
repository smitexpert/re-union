# SWAATC Reunion Landing Page — Design

Date: 2026-09-26
Status: Approved

## Goal

A mobile-first landing page for the Social Work Alumni Association of Tejgaon
College (SWAATC) reunion, served at `/` of the Laravel app. It introduces the
association, lists event details, and drives visitors to an external
registration page.

## Decisions (from brainstorming)

- **Delivery:** Inertia + React page replacing the current `resources/js/pages/Home.jsx`. No backend changes.
- **No form on the page.** The "Register Now" CTA links out to an external
  registration page (Google Form or separate route — URL supplied later).
- **Content placeholders:** date, time, entry info, and About copy are clearly
  marked placeholders to be filled in later.
- **Optional sections dropped:** testimonials, sponsors strip, closing CTA band.
- **Visual direction C:** light/clean hero — white background, blue accents,
  logo front and center. Brand blue `#00BFFF` reserved for CTAs and accents;
  black for text; light grey for the Event Details section.

## Page structure (single vertical scroll)

1. **Header** — sticky, white; small centered logo (`/logo.png`); hamburger on
   the right toggling a dropdown with anchor links: About, Event Details,
   Contact. Plain `useState`, closes on link click.
2. **Hero** (white) — logo, headline "Reconnect, Reflect, Rejoice: SWAATC
   Reunion 2024", subtitle "Join your fellow Tejgaon College Social Work alumni
   for a historic gathering.", full-width `#00BFFF` "Register Now" button
   linking to `REGISTER_URL` (constant at top of `Home.jsx`, placeholder value).
3. **About** (white) — two short placeholder paragraphs about SWAATC and the
   reunion's significance. No logo here (hero already shows it).
4. **Event Details** (light grey `#f5f6f7`) — three stacked cards with inline
   SVG icons: Date & Time `[TBD]`, Location "Tejgaon College Auditorium &
   Campus", Entry `[TBD]`.
5. **Footer** (black) — Facebook icon + placeholder group link, contact email
   placeholder, "© 2024 SWAATC".

## Implementation notes

- **Files:** `resources/js/pages/Home.jsx` composes section components from
  `resources/js/components/landing/`: `Header.jsx`, `Hero.jsx`, `About.jsx`,
  `EventDetails.jsx`, `Footer.jsx`.
- **Styling:** Tailwind 4 via the existing Vite plugin. Brand tokens as CSS
  variables in `resources/css/app.css`: `--swaatc-blue: #00BFFF`, plus black
  and `#f5f6f7`.
- **Fonts:** headings Instrument Sans (already loaded via Bunny Fonts);
  body **Lora** serif, added to the `fonts` array in `vite.config.js` — per the
  spec's sans-headings / serif-body split.
- **Responsive:** mobile-first; verified at ~375px width. Sections stack
  vertically; no horizontal scroll.
- **Error handling:** no form, no server state; only client state is the menu
  toggle. Nothing to fail beyond that.

## Testing

- Feature test: `GET /` returns 200 and renders the `Home` Inertia page
  (replacing the skeleton's home test if one exists).
- Manual check with `npm run dev` at mobile width for layout, contrast, and
  CTA prominence.