import { type ReactNode } from "react";

/**
 * Shared shell for the three auth screens (register/login/forgot-password): a deep-purple
 * "stub" panel on one side and the form on the other, divided by a column of gold dots that
 * echo a ticket's tear-off perforation — the one signature element every auth screen shares.
 */
export function AuthLayout({
    eyebrow,
    headline,
    tagline,
    children,
}: {
    eyebrow: string;
    headline: string;
    tagline: string;
    children: ReactNode;
}) {
    return (
        <div className="min-h-screen bg-white font-sans text-deep-purple lg:grid lg:grid-cols-[minmax(0,5fr)_28px_minmax(0,7fr)]">
            <div className="relative isolate flex flex-col justify-between overflow-hidden bg-deep-purple px-8 py-10 text-white sm:px-12 sm:py-14 lg:px-14 lg:py-16">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            "radial-gradient(circle at 1px 1px, white 1px, transparent 0)",
                        backgroundSize: "22px 22px",
                    }}
                />

                <div className="relative">
                    <p className="font-condensed text-xs font-semibold uppercase tracking-[0.35em] text-gold">
                        {eyebrow}
                    </p>
                    <h1 className="mt-6 font-display text-4xl leading-[1.1] sm:text-5xl">
                        {headline}
                    </h1>
                    <p className="mt-5 max-w-sm font-sans text-base text-white/75">
                        {tagline}
                    </p>
                </div>

                <div className="relative mt-12 flex items-center gap-3 font-condensed text-xs uppercase tracking-[0.3em] text-white/50 lg:mt-0">
                    <span className="h-px flex-1 bg-white/20" />
                    Genius Behind the Brands
                    <span className="h-px flex-1 bg-white/20" />
                </div>
            </div>

            {/* Perforation: a ticket's tear-off edge, running along the panel seam. */}
            <div
                aria-hidden="true"
                className="hidden bg-white lg:flex lg:flex-col lg:items-center lg:justify-evenly lg:py-10"
            >
                {Array.from({ length: 14 }).map((_, i) => (
                    <span
                        key={i}
                        className="h-2.5 w-2.5 shrink-0 rounded-full bg-gold"
                    />
                ))}
            </div>

            <div className="flex items-center justify-center px-6 py-12 sm:px-12 lg:px-16">
                <div className="w-full max-w-md">{children}</div>
            </div>
        </div>
    );
}
