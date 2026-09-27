import { Head } from '@inertiajs/react';
import Header from '@/components/landing/Header';
import Hero from '@/components/landing/Hero';
import About from '@/components/landing/About';
import Footer from '@/components/landing/Footer';
import StickyCta from '@/components/landing/StickyCta';

const REGISTER_URL = '/register';

export default function Home() {
    return (
        <>
            <Head title="SWAATC — Social Work Alumni Association">
                <meta
                    name="description"
                    content="Join the Social Work Alumni Association of Tejgaon College (SWAATC) — reconnect with batchmates, honour our teachers, and celebrate on campus in Dhaka."
                />
            </Head>
            <div className="min-h-screen bg-white font-sans text-black">
                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[60] focus:rounded-lg focus:bg-black focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
                >
                    Skip to content
                </a>
                <Header registerUrl={REGISTER_URL} />
                <main id="main">
                    <Hero registerUrl={REGISTER_URL} />
                    <About />
                </main>
                <Footer />
                <StickyCta registerUrl={REGISTER_URL} />
            </div>
        </>
    );
}
