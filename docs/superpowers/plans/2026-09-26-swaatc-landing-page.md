# SWAATC Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the skeleton `Home` Inertia page with a mobile-first SWAATC reunion landing page at `/` — header, hero with external-register CTA, about, event details, footer.

**Architecture:** Static Inertia + React page composed of five small section components. No backend changes beyond dropping the unused `greeting` prop from the `/` route. The only client state is the header menu toggle. Styling is Tailwind 4 with brand tokens declared in `@theme`.

**Tech Stack:** Laravel 13, Inertia 3 (React), Tailwind CSS 4 (Vite plugin), Bunny Fonts (via `laravel-vite-plugin/fonts`), PHPUnit.

**Note on commits:** this directory is **not** a git repository, so there are no commit steps. If a repo gets initialized later, commit after each task.

**Spec:** `docs/superpowers/specs/2026-09-26-swaatc-landing-page-design.md`

---

### Task 1: Update the home-route feature test (TDD)

**Files:**
- Create: `tests/Feature/HomeTest.php`
- Delete: `tests/Feature/ExampleTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HomeTest.php`:

```php
<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_the_landing_page_renders_the_home_component(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->missing('greeting')
        );
    }
}
```

Then delete `tests/Feature/ExampleTest.php` (its `has('greeting')` assertion contradicts this test).

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=HomeTest`
Expected: FAIL — the route currently passes `greeting`, so `->missing('greeting')` is false.

- [ ] **Step 3: Drop the greeting prop from the route**

Replace the body of `routes/web.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=HomeTest`
Expected: PASS (1 test). The page component itself is still the old `Home.jsx` — it simply no longer receives `greeting`; Task 8 replaces it.

---

### Task 2: Brand tokens and fonts

**Files:**
- Modify: `resources/css/app.css`
- Modify: `vite.config.js`

- [ ] **Step 1: Add serif font and brand colors to `app.css`**

Replace the `@theme` block at the bottom of `resources/css/app.css` with:

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
    --font-serif: 'Lora', Georgia, 'Times New Roman', serif;

    --color-brand: #00BFFF;
    --color-mist: #f5f6f7;
}
```

This registers the utilities used later: `font-serif`, `bg-brand` / `text-brand` / `bg-brand/10`, and `bg-mist`.

- [ ] **Step 2: Load Lora via Bunny Fonts in `vite.config.js`**

In the `fonts` array of the `laravel()` plugin, add a second entry so it reads:

```js
fonts: [
    bunny('Instrument Sans', {
        weights: [400, 500, 600],
    }),
    bunny('Lora', {
        weights: [400, 500],
    }),
],
```

- [ ] **Step 3: Verify the build still succeeds**

Run: `npm run build`
Expected: build completes without errors.

---

### Task 3: Header component

**Files:**
- Create: `resources/js/components/landing/Header.jsx`

- [ ] **Step 1: Create the Header component**

```jsx
import { useState } from 'react';

const LINKS = [
    { label: 'About', href: '#about' },
    { label: 'Event Details', href: '#details' },
    { label: 'Contact', href: '#contact' },
];

export default function Header() {
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-50 border-b border-gray-100 bg-white/95 backdrop-blur">
            <div className="relative mx-auto flex h-14 max-w-md items-center justify-center px-4">
                <a href="#top" aria-label="Back to top">
                    <img src="/logo.png" alt="SWAATC logo" className="h-10 w-10" />
                </a>
                <button
                    type="button"
                    aria-label="Toggle menu"
                    aria-expanded={open}
                    onClick={() => setOpen((v) => !v)}
                    className="absolute right-4 flex h-10 w-10 flex-col items-center justify-center gap-1.5"
                >
                    <span className="h-0.5 w-5 bg-black" />
                    <span className="h-0.5 w-5 bg-black" />
                    <span className="h-0.5 w-5 bg-black" />
                </button>
            </div>
            {open && (
                <nav className="border-t border-gray-100 bg-white px-6 py-3">
                    <ul className="space-y-1">
                        {LINKS.map((link) => (
                            <li key={link.href}>
                                <a
                                    href={link.href}
                                    onClick={() => setOpen(false)}
                                    className="block rounded-lg px-2 py-2 text-sm font-semibold hover:bg-gray-50"
                                >
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </header>
    );
}
```

---

### Task 4: Hero component

**Files:**
- Create: `resources/js/components/landing/Hero.jsx`

- [ ] **Step 1: Create the Hero component**

```jsx
export default function Hero({ registerUrl }) {
    return (
        <section id="top" className="px-6 pb-16 pt-12 text-center">
            <img
                src="/logo.png"
                alt="SWAATC — Social Work Alumni Association, Tejgaon College, Dhaka"
                className="mx-auto h-36 w-36"
            />
            <h1 className="mt-6 text-3xl font-bold leading-tight tracking-tight">
                Reconnect, Reflect, Rejoice:{' '}
                <span className="text-brand">SWAATC Reunion 2024</span>
            </h1>
            <p className="mx-auto mt-3 max-w-xs font-serif text-base leading-relaxed text-gray-600">
                Join your fellow Tejgaon College Social Work alumni for a historic
                gathering.
            </p>
            <a
                href={registerUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-8 block w-full rounded-xl bg-brand px-6 py-4 text-base font-bold text-black transition hover:brightness-95"
            >
                Register Now
            </a>
        </section>
    );
}
```

---

### Task 5: About component

**Files:**
- Create: `resources/js/components/landing/About.jsx`

- [ ] **Step 1: Create the About component**

```jsx
export default function About() {
    return (
        <section id="about" className="border-t border-gray-100 px-6 py-16">
            <h2 className="text-center text-xs font-bold uppercase tracking-widest text-brand">
                About the Association
            </h2>
            <div className="mx-auto mt-4 max-w-sm space-y-4 font-serif text-base leading-relaxed text-gray-700">
                <p>
                    The Social Work Alumni Association of Tejgaon College (SWAATC) unites
                    graduates of the Department of Social Work who share a lasting
                    commitment to community, service, and one another.
                </p>
                <p>
                    The 2024 reunion marks a historic gathering — a day to reconnect with
                    batchmates, honour our teachers, and celebrate the department&apos;s
                    legacy on the campus where it all began. [Placeholder copy — replace
                    with the approved text.]
                </p>
            </div>
        </section>
    );
}
```

---

### Task 6: Event Details component

**Files:**
- Create: `resources/js/components/landing/EventDetails.jsx`

- [ ] **Step 1: Create the EventDetails component**

```jsx
function CalendarIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-6 w-6">
            <path strokeLinecap="round" d="M6.75 3v2.25M17.25 3v2.25" />
            <path d="M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
        </svg>
    );
}

function MapPinIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-6 w-6">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
        </svg>
    );
}

function TicketIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-6 w-6">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4V9Z" />
            <path strokeLinecap="round" strokeDasharray="2 3" d="M14.5 7v10" />
        </svg>
    );
}

const CARDS = [
    { Icon: CalendarIcon, title: 'Date & Time', text: '[Date TBD] · [Time TBD]' },
    { Icon: MapPinIcon, title: 'Location', text: 'Tejgaon College Auditorium & Campus, Dhaka' },
    { Icon: TicketIcon, title: 'Entry', text: '[Registration / ticket info TBD]' },
];

export default function EventDetails() {
    return (
        <section id="details" className="bg-mist px-6 py-16">
            <h2 className="text-center text-xs font-bold uppercase tracking-widest">
                Event Details
            </h2>
            <div className="mx-auto mt-4 max-w-sm space-y-3">
                {CARDS.map(({ Icon, title, text }) => (
                    <div
                        key={title}
                        className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4"
                    >
                        <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                            <Icon />
                        </div>
                        <div>
                            <p className="text-sm font-bold">{title}</p>
                            <p className="text-sm text-gray-600">{text}</p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}
```

---

### Task 7: Footer component

**Files:**
- Create: `resources/js/components/landing/Footer.jsx`

- [ ] **Step 1: Create the Footer component**

```jsx
function FacebookIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5">
            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12Z" />
        </svg>
    );
}

export default function Footer() {
    return (
        <footer id="contact" className="bg-black px-6 py-12 text-center text-white">
            <a
                href="https://facebook.com/groups/[swaatc-group-placeholder]"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="SWAATC Facebook group"
                className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20"
            >
                <FacebookIcon />
            </a>
            <p className="mt-4 font-serif text-sm text-gray-300">
                Questions? Email us at{' '}
                <a href="mailto:hello@swaatc.org" className="text-brand underline">
                    hello@swaatc.org
                </a>
            </p>
            <p className="mt-4 text-xs text-gray-500">
                © 2024 SWAATC — Social Work Alumni Association, Tejgaon College, Dhaka
            </p>
        </footer>
    );
}
```

---

### Task 8: Compose the page

**Files:**
- Modify: `resources/js/pages/Home.jsx` (full replace)

- [ ] **Step 1: Replace Home.jsx with the page shell**

```jsx
import { Head } from '@inertiajs/react';
import Header from '@/components/landing/Header';
import Hero from '@/components/landing/Hero';
import About from '@/components/landing/About';
import EventDetails from '@/components/landing/EventDetails';
import Footer from '@/components/landing/Footer';

// External registration page (Google Form or in-app route). Replace when ready.
const REGISTER_URL = 'https://example.com/register';

export default function Home() {
    return (
        <>
            <Head title="SWAATC Reunion 2024" />
            <div className="min-h-screen bg-white font-sans text-black">
                <Header />
                <main>
                    <Hero registerUrl={REGISTER_URL} />
                    <About />
                    <EventDetails />
                </main>
                <Footer />
            </div>
        </>
    );
}
```

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: PASS (all tests, including `HomeTest`).

---

### Task 9: Visual verification at mobile width

- [ ] **Step 1: Build the frontend**

Run: `npm run build`
Expected: build completes without errors.

- [ ] **Step 2: Serve the app and check the page**

Run `php artisan serve` (or use the existing docker/nginx setup) and open the app URL at a mobile viewport (~375px, e.g. browser devtools device mode).

Expected, top to bottom:
1. Sticky white header with small centered logo and hamburger; tapping the hamburger opens a menu with About / Event Details / Contact that scrolls to the right section and closes.
2. White hero: logo, "Reconnect, Reflect, Rejoice: SWAATC Reunion 2024" (reunion name in brand blue), serif subtitle, full-width blue **Register Now** button (most prominent element).
3. White About section with two serif paragraphs.
4. Light-grey Event Details with three white cards (calendar / map pin / ticket icons).
5. Black footer: Facebook icon, email link, copyright line.
6. No horizontal scroll at 375px; all sections stack cleanly.