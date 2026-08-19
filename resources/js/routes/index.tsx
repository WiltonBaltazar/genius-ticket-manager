import { createRoute, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "./__root";
import { getJson } from "../lib/auth";
import { useCart } from "../lib/cart";
import { TicketTypeSelector } from "../components/checkout/TicketTypeSelector";
import { CheckoutDetailsForm } from "../components/checkout/CheckoutDetailsForm";
import { formatEventDate } from "../lib/formatEventDate";

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
    description: string | null;
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
        <div className="min-h-screen bg-white font-sans text-deep-purple">
            <header className="border-b border-deep-purple/10 px-6 py-4">
                <img
                    src="/images/logo.png"
                    alt="Genius Behind the Brands"
                    className="h-9 w-auto"
                />
            </header>

            <section className="relative overflow-hidden bg-deep-purple">
                {event.hero_image_url && (
                    <img
                        src={event.hero_image_url}
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                )}
                <div
                    aria-hidden="true"
                    className={
                        event.hero_image_url
                            ? "absolute inset-0 bg-gradient-to-t from-deep-purple via-deep-purple/75 to-deep-purple/25"
                            : "absolute inset-0 opacity-[0.07]"
                    }
                    style={
                        event.hero_image_url
                            ? undefined
                            : {
                                  backgroundImage:
                                      "radial-gradient(circle at 1px 1px, white 1px, transparent 0)",
                                  backgroundSize: "22px 22px",
                              }
                    }
                />
                <div className="relative mx-auto max-w-2xl px-6 py-16 sm:py-24">
                    <h1 className="font-display text-4xl leading-[1.1] text-white sm:text-5xl">
                        {event.name}
                    </h1>
                    <dl className="mt-6 flex flex-wrap gap-x-10 gap-y-4">
                        <div>
                            <dt className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                                Data
                            </dt>
                            <dd className="mt-1 font-sans text-base text-white/90">
                                {formatEventDate(event.start_date, event.end_date)}
                            </dd>
                        </div>
                        {event.venue && (
                            <div>
                                <dt className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                                    Local
                                </dt>
                                <dd className="mt-1 font-sans text-base text-white/90">
                                    {event.venue}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>
            </section>

            <div className="mx-auto max-w-2xl px-6 py-12">
                {event.description && (
                    <>
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                            Sobre o evento
                        </p>
                        <div
                            className="mt-3 font-sans text-base leading-relaxed text-deep-purple/70"
                            dangerouslySetInnerHTML={{
                                __html: event.description,
                            }}
                        />
                    </>
                )}

                <div className="mt-12">
                    <div className="flex items-baseline justify-between border-b border-deep-purple/10 pb-3">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                            Bilhetes
                        </p>
                    </div>

                    <TicketTypeSelector
                        eventId={event.id}
                        eventSlug={event.slug}
                        event={{
                            startDate: event.start_date,
                            endDate: event.end_date,
                        }}
                        ticketTypes={data.ticket_types.map((tt) => ({
                            id: tt.id,
                            name: tt.name,
                            description: tt.description,
                            price: tt.price,
                            availableQuantity: tt.available_quantity,
                        }))}
                    />
                </div>

                {inCartForThisEvent && (
                    <div className="mt-12 border-t border-deep-purple/10 pt-10">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                            Último passo
                        </p>
                        <h2 className="mt-3 font-display text-3xl text-deep-purple">
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

                {window.__CHECKOUT_CONFIG__.whatsappNumber && (
                    <div className="mt-12 border-t border-deep-purple/10 pt-10">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                            Dúvidas?
                        </p>
                        <p className="mt-3 font-sans text-base text-deep-purple/70">
                            Fale connosco no WhatsApp.
                        </p>
                        <a
                            href={`https://wa.me/${window.__CHECKOUT_CONFIG__.whatsappNumber}?text=${encodeURIComponent(`Olá! Tenho uma dúvida sobre o evento ${event.name}.`)}`}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 inline-block rounded-full bg-deep-purple px-6 py-2.5 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple"
                        >
                            Abrir WhatsApp
                        </a>
                    </div>
                )}
            </div>
        </div>
    );
}
