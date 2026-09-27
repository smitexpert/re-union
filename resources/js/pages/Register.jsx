import { Head } from '@inertiajs/react';
import ClosedNotice from '@/components/registration/ClosedNotice';
import Wizard from '@/components/registration/Wizard';
import PageLogoHeader from '@/components/PageLogoHeader';

export default function Register({ mode, reunion, feeBreakdown, banglaMessage, paymentLookupUrl }) {
    return (
        <>
            <Head title="Register — SWAATC Reunion" />
            <PageLogoHeader />
            <main className="min-h-screen bg-mist px-4 py-8 sm:py-12">
                <div className="mx-auto max-w-md">
                    {mode === 'closed' ? (
                        <ClosedNotice banglaMessage={banglaMessage} paymentLookupUrl={paymentLookupUrl} />
                    ) : (
                        <Wizard reunion={reunion} feeBreakdown={feeBreakdown} />
                    )}
                </div>
            </main>
        </>
    );
}
