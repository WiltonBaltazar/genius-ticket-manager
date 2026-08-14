import { createRoute, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "./__root";
import { getJson } from "../lib/auth";
import { useCart } from "../lib/cart";
import { TicketTypeSelector } from "../components/checkout/TicketTypeSelector";
import { CheckoutDetailsForm } from "../components/checkout/CheckoutDetailsForm";

type EventDetail = {
    id: string;
    name: string;
    slug: string;
    venue: string | null;
    start_date: string;
    end_date: string | null;
    description: string | null;
    hero_image_url: string | null;
};

type TicketTypePayload = {
    id: string;
    name: string;
    price: string;
    available_quantity: number;
};

type EventPayload = {
    event: EventDetail | null;
    ticket_types: TicketTypePayload[];
};

export const homeRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/",
    component: HomePage,
});

function HomePage() {
    const [data, setData] = useState<EventPayload | null>(null);
    const cart = useCart();
    const navigate = useNavigate();

    useEffect(() => {
        getJson<EventPayload>("/").then(setData);
    }, []);

    if (!data) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple/50">
                A carregar…
            </div>
        );
    }

    if (!data.event) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple">
                <img
                    src="/images/logo.png"
                    alt="Genius Behind the Brands"
                    className="mx-auto mb-8 h-auto w-48"
                />
                <p>Não há eventos à venda neste momento — volte em breve.</p>
            </div>
        );
    }

    const { event } = data;
    const inCartForThisEvent =
        cart.eventId === event.id && cart.items.length > 0;

    return (
        <div className="mx-auto max-w-2xl px-6 py-12">
            <img
                src="/images/logo.png"
                alt="Genius Behind the Brands"
                className="mb-8 h-auto w-40"
            />

            {event.hero_image_url && (
                <img
                    src={event.hero_image_url}
                    alt=""
                    className="mb-6 aspect-[16/9] w-full rounded-lg object-cover"
                />
            )}

            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                {new Date(event.start_date).toLocaleDateString("pt", {
                    dateStyle: "long",
                })}
            </p>
            <h1 className="mt-3 font-display text-4xl text-deep-purple">
                {event.name}
            </h1>
            {event.venue && (
                <p className="mt-2 font-sans text-base text-deep-purple/70">
                    {event.venue}
                </p>
            )}
            {event.description && (
                <div
                    className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70"
                    dangerouslySetInnerHTML={{ __html: event.description }}
                />
            )}

            <TicketTypeSelector
                eventId={event.id}
                eventSlug={event.slug}
                ticketTypes={data.ticket_types.map((tt) => ({
                    id: tt.id,
                    name: tt.name,
                    price: tt.price,
                    availableQuantity: tt.available_quantity,
                }))}
            />

            {inCartForThisEvent && (
                <div className="mt-8 border-t border-deep-purple/10 pt-8">
                    <h2 className="font-display text-2xl text-deep-purple">
                        Os seus dados
                    </h2>
                    <div className="mt-6">
                        <CheckoutDetailsForm
                            onSubmitted={(orderId) =>
                                navigate({ to: `/orders/${orderId}` })
                            }
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
