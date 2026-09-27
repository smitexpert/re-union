import { useEffect, useRef, useState } from 'react';

/**
 * Fades content in the first time it scrolls into view.
 * Motion is transform/opacity only and disabled for prefers-reduced-motion.
 */
export default function Reveal({ children, delay = 0, className = '' }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el || typeof IntersectionObserver === 'undefined') {
            setVisible(true);
            return undefined;
        }
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.15 },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
            className={`transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none ${
                visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'
            } ${className}`}
        >
            {children}
        </div>
    );
}