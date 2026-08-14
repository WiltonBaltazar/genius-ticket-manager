import { createRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "../__root";
import { getJson } from "../../lib/auth";
import { TicketTypeSelector } from "../../components/checkout/TicketTypeSelector";

type EventPayload = {
    event: {
        id: string;
        name: string;
        slug: string;
        venue: string | null;
        start_date: string;
        description: string | null;
        hero_image_url: string | null;
    };
    ticket_types: {
        id: string;
        name: string;
        price: string;
        available_quantity: number;
    }[];
};

export const eventShowRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/events/$slug",
    component: EventPage,
});

function EventPage() {
    const { slug } = eventShowRoute.useParams();
    const [data, setData] = useState<EventPayload | null>(null);
    const [notFound, setNotFound] = useState(false);

    useEffect(() => {
        getJson<EventPayload>(`/events/${slug}`)
            .then(setData)
            .catch(() => setNotFound(true));
    }, [slug]);

    if (notFound) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple">
                <p>This event isn't available.</p>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple/50">
                Loading…
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-2xl px-6 py-12">
            {data.event.hero_image_url && (
                <img
                    src={data.event.hero_image_url}
                    alt=""
                    className="mb-6 aspect-[16/9] w-full rounded-lg object-cover"
                />
            )}

            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                {new Date(data.event.start_date).toLocaleDateString(undefined, {
                    dateStyle: "long",
                })}
            </p>
            <h1 className="mt-3 font-display text-4xl text-deep-purple">
                {data.event.name}
            </h1>
            {data.event.venue && (
                <p className="mt-2 font-sans text-base text-deep-purple/70">
                    {data.event.venue}
                </p>
            )}
            {data.event.description && (
                <p className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70">
                    {data.event.description}
                </p>
            )}

            <TicketTypeSelector
                eventId={data.event.id}
                eventSlug={data.event.slug}
                ticketTypes={data.ticket_types.map((tt) => ({
                    id: tt.id,
                    name: tt.name,
                    price: tt.price,
                    availableQuantity: tt.available_quantity,
                }))}
            />
        </div>
    );
}
