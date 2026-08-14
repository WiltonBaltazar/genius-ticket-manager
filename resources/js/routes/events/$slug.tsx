import { createRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "../__root";
import { getJson } from "../../lib/auth";
import { useCart } from "../../lib/cart";
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
    const cart = useCart();

    useEffect(() => {
        getJson<EventPayload>(`/events/${slug}`)
            .then(setData)
            .catch(() => setNotFound(true));
    }, [slug]);

    if (notFound) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple">
                <p>Este evento não está disponível.</p>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple/50">
                A carregar…
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-2xl px-6 py-12">
            <img
                src="/images/logo.png"
                alt="Genius Behind the Brands"
                className="mb-8 h-auto w-40"
            />

            {data.event.hero_image_url && (
                <img
                    src={data.event.hero_image_url}
                    alt=""
                    className="mb-6 aspect-[16/9] w-full rounded-lg object-cover"
                />
            )}

            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                {new Date(data.event.start_date).toLocaleDateString("pt", {
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
                <div
                    className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70"
                    dangerouslySetInnerHTML={{ __html: data.event.description }}
                />
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

            {cart.eventId === data.event.id && cart.items.length > 0 && (
                <Link
                    to="/checkout"
                    className="mt-6 block w-full rounded-md bg-deep-purple px-4 py-3 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple"
                >
                    Continuar para o pagamento — MZN {cart.total.toFixed(2)}
                </Link>
            )}
        </div>
    );
}
