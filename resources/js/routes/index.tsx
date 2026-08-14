import { createRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "./__root";
import { getJson } from "../lib/auth";

type EventSummary = {
    id: string;
    name: string;
    slug: string;
    venue: string | null;
    start_date: string;
    end_date: string | null;
    hero_image_url: string | null;
    starting_price: string | null;
};

type EventsPayload = {
    events: EventSummary[];
};

export const homeRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/",
    component: HomePage,
});

function HomePage() {
    const [data, setData] = useState<EventsPayload | null>(null);

    useEffect(() => {
        getJson<EventsPayload>("/").then(setData);
    }, []);

    return (
        <div className="mx-auto max-w-5xl px-6 py-12">
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                Genius Behind the Brands
            </p>
            <h1 className="mt-3 font-display text-4xl text-deep-purple">
                Upcoming events
            </h1>

            {!data ? (
                <p className="mt-8 font-sans text-base text-deep-purple/50">
                    Loading…
                </p>
            ) : data.events.length === 0 ? (
                <p className="mt-8 font-sans text-base text-deep-purple/70">
                    No upcoming events right now — check back soon.
                </p>
            ) : (
                <div className="mt-8 grid gap-6 sm:grid-cols-2">
                    {data.events.map((event) => (
                        <Link
                            key={event.id}
                            to="/events/$slug"
                            params={{ slug: event.slug }}
                            className="group block overflow-hidden rounded-lg border border-deep-purple/10 transition-colors hover:border-gold"
                        >
                            {event.hero_image_url ? (
                                <img
                                    src={event.hero_image_url}
                                    alt=""
                                    className="aspect-[16/9] w-full object-cover"
                                />
                            ) : (
                                <div className="aspect-[16/9] w-full bg-deep-purple/5" />
                            )}
                            <div className="p-4">
                                <p className="font-condensed text-xs font-semibold uppercase tracking-wide text-gold">
                                    {new Date(
                                        event.start_date,
                                    ).toLocaleDateString(undefined, {
                                        dateStyle: "long",
                                    })}
                                </p>
                                <p className="mt-1 font-sans text-lg font-semibold text-deep-purple group-hover:text-gold">
                                    {event.name}
                                </p>
                                {event.venue && (
                                    <p className="mt-1 font-sans text-sm text-deep-purple/70">
                                        {event.venue}
                                    </p>
                                )}
                                <p className="mt-3 font-condensed text-sm font-semibold uppercase tracking-wide text-deep-purple">
                                    {event.starting_price
                                        ? `From MZN ${event.starting_price}`
                                        : "Tickets not yet on sale"}
                                </p>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
