import { createRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { rootRoute } from "../__root";
import { getJson } from "../../lib/auth";
import { useCart } from "../../lib/cart";
import { TicketTypeSelector } from "../../components/checkout/TicketTypeSelector";
import { formatEventDate } from "../../lib/formatEventDate";

type EventPayload = {
    event: {
        id: string;
        name: string;
        slug: string;
        venue: string | null;
        start_date: string;
        end_date: string | null;
        description: string | null;
        hero_image_url: string | null;
    };
    ticket_types: {
        id: string;
        name: string;
        description: string | null;
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

    const itemCount = cart.items.reduce((sum, item) => sum + item.quantity, 0);
    const showCartBar = cart.eventId === data.event.id && itemCount > 0;

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
                {data.event.hero_image_url && (
                    <img
                        src={data.event.hero_image_url}
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                )}
                <div
                    aria-hidden="true"
                    className={
                        data.event.hero_image_url
                            ? "absolute inset-0 bg-gradient-to-t from-deep-purple via-deep-purple/75 to-deep-purple/25"
                            : "absolute inset-0 opacity-[0.07]"
                    }
                    style={
                        data.event.hero_image_url
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
                        {data.event.name}
                    </h1>
                    <dl className="mt-6 flex flex-wrap gap-x-10 gap-y-4">
                        <div>
                            <dt className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                                Data
                            </dt>
                            <dd className="mt-1 font-sans text-base text-white/90">
                                {formatEventDate(
                                    data.event.start_date,
                                    data.event.end_date,
                                )}
                            </dd>
                        </div>
                        {data.event.venue && (
                            <div>
                                <dt className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                                    Local
                                </dt>
                                <dd className="mt-1 font-sans text-base text-white/90">
                                    {data.event.venue}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>
            </section>

            <div
                className={`mx-auto max-w-2xl px-6 py-12 ${showCartBar ? "pb-32" : ""}`}
            >
                {data.event.description && (
                    <>
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                            Sobre o evento
                        </p>
                        <div
                            className="mt-3 font-sans text-base leading-relaxed text-deep-purple/70"
                            dangerouslySetInnerHTML={{
                                __html: data.event.description,
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
                        eventId={data.event.id}
                        eventSlug={data.event.slug}
                        event={{
                            startDate: data.event.start_date,
                            endDate: data.event.end_date,
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

                {window.__CHECKOUT_CONFIG__.whatsappNumber && (
                    <div className="mt-12 border-t border-deep-purple/10 pt-10">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                            Dúvidas?
                        </p>
                        <p className="mt-3 font-sans text-base text-deep-purple/70">
                            Fale connosco no WhatsApp.
                        </p>
                        <a
                            href={`https://wa.me/${window.__CHECKOUT_CONFIG__.whatsappNumber}?text=${encodeURIComponent(`Olá! Tenho uma dúvida sobre o evento ${data.event.name}.`)}`}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 inline-block rounded-full bg-deep-purple px-6 py-2.5 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple"
                        >
                            Abrir WhatsApp
                        </a>
                    </div>
                )}
            </div>

            {showCartBar && (
                <div className="fixed inset-x-0 bottom-0 z-10 border-t border-white/10 bg-deep-purple px-6 py-4 shadow-[0_-8px_24px_rgba(0,0,0,0.15)]">
                    <div className="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        {/* whitespace-nowrap: on a narrow phone this row is one gap-4 away
                            from the button; without it, "MZN 400.00" can break mid-price
                            instead of the row wrapping as a whole. */}
                        <p className="font-sans text-sm whitespace-nowrap text-white/80">
                            {itemCount}{" "}
                            {itemCount === 1 ? "bilhete" : "bilhetes"}
                            <span className="mx-2 text-white/30">·</span>
                            <span className="font-display text-lg text-white">
                                MZN {cart.total.toFixed(2)}
                            </span>
                        </p>
                        <Link
                            to="/checkout"
                            className="shrink-0 rounded-full bg-gold px-6 py-2.5 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-deep-purple transition-colors hover:bg-gold-hover"
                        >
                            Continuar
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
