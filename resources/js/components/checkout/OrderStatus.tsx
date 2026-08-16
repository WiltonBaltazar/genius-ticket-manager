type OrderPayload = {
    id: string;
    status: "pending" | "paid" | "expired";
    total_amount: string;
    expires_at?: string;
    items: {
        ticket_type_name: string;
        event_date: string | null;
        quantity: number;
        unit_price: string;
        subtotal: string;
    }[];
    tickets: {
        id: string;
        ticket_type_name: string;
        event_date: string | null;
        pdf_url: string;
    }[];
};

function dayLabel(eventDate: string | null): string | null {
    if (!eventDate) return null;
    return new Date(`${eventDate}T00:00:00`).toLocaleDateString("pt", {
        day: "2-digit",
        month: "short",
    });
}

const STATUS_LABEL: Record<OrderPayload["status"], string> = {
    paid: "Pago",
    pending: "Aguarda pagamento",
    expired: "Expirado",
};

const STATUS_BADGE_CLASS: Record<OrderPayload["status"], string> = {
    paid: "bg-gold text-deep-purple",
    pending: "border border-white/30 text-white/90",
    expired: "bg-red text-white",
};

/** Renders an order's pending/paid/expired states distinctly, as a single ticket-shaped card (spec.md FR-018, User Stories 3-5). */
export function OrderStatus({ order }: { order: OrderPayload }) {
    return (
        <div className="overflow-hidden rounded-2xl border border-deep-purple/10 shadow-sm">
            <div className="relative bg-deep-purple px-6 py-5">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                    Pedido #{order.id.slice(0, 8).toUpperCase()}
                </p>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    {/* whitespace-nowrap + shrink-0: a rounded-full badge with wrapped text
                        looks broken, not just tight — if this row is ever too narrow for both
                        the badge and the expiry text, the row should wrap as a whole (the
                        expiry text drops to its own line) rather than the pill's own label
                        breaking mid-word. */}
                    <span
                        role="status"
                        className={`shrink-0 rounded-full px-3 py-1 font-condensed text-xs font-semibold whitespace-nowrap uppercase tracking-wide ${STATUS_BADGE_CLASS[order.status]}`}
                    >
                        {STATUS_LABEL[order.status]}
                    </span>
                    {order.status === "pending" && order.expires_at && (
                        <p className="font-sans text-xs text-white/60">
                            Expira às{" "}
                            {new Date(order.expires_at).toLocaleString("pt", {
                                dateStyle: "short",
                                timeStyle: "short",
                            })}
                        </p>
                    )}
                </div>
            </div>

            {/* Torn-edge seam, echoing the ticket-stub motif from the ticket selector and the PDF ticket itself. */}
            <div
                aria-hidden="true"
                className="relative h-4 bg-deep-purple"
            >
                <span className="absolute inset-x-4 top-1/2 -translate-y-1/2 border-t-2 border-dashed border-white/20" />
                <span className="absolute -left-2 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full bg-white" />
                <span className="absolute -right-2 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full bg-white" />
            </div>

            <div className="bg-white px-6 py-6">
                {order.status === "paid" && (
                    <p className="font-sans text-sm text-deep-purple/70">
                        Pagamento confirmado — os seus bilhetes estão prontos
                        abaixo.
                    </p>
                )}
                {order.status === "pending" && (
                    <p className="font-sans text-sm text-deep-purple/70">
                        Complete uma das opções abaixo para confirmar o seu
                        pedido.
                    </p>
                )}
                {order.status === "expired" && (
                    <p className="font-sans text-sm text-deep-purple/70">
                        Este pedido expirou antes de o pagamento ser
                        concluído e os bilhetes foram novamente
                        disponibilizados para venda.
                    </p>
                )}

                <ul className="mt-5 divide-y divide-deep-purple/10 rounded-md border border-deep-purple/10">
                    {order.items.map((item, i) => (
                        <li
                            key={i}
                            className="flex items-center justify-between px-4 py-3 font-sans text-sm text-deep-purple"
                        >
                            <span>
                                {item.quantity} × {item.ticket_type_name}
                                {dayLabel(item.event_date) && (
                                    <span className="text-deep-purple/50">
                                        {" "}
                                        ({dayLabel(item.event_date)})
                                    </span>
                                )}
                            </span>
                            <span>MZN {item.subtotal}</span>
                        </li>
                    ))}
                    <li className="flex items-center justify-between px-4 py-3 font-sans text-sm font-semibold text-deep-purple">
                        <span>Total</span>
                        <span>MZN {order.total_amount}</span>
                    </li>
                </ul>

                {order.status === "paid" && order.tickets.length > 0 && (
                    <div className="mt-6">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple/50">
                            Os seus bilhetes
                        </p>
                        <ul className="mt-3 space-y-2">
                            {order.tickets.map((ticket, i) => (
                                <li key={ticket.id}>
                                    <a
                                        href={ticket.pdf_url}
                                        className="flex items-center overflow-hidden rounded-lg border border-deep-purple/20 transition-colors hover:border-gold"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="flex w-6 shrink-0 flex-col items-center justify-center gap-1.5 self-stretch border-r border-dashed border-deep-purple/20 bg-deep-purple/[0.03] py-3"
                                        >
                                            {Array.from({ length: 3 }).map(
                                                (_, dotIndex) => (
                                                    <span
                                                        key={dotIndex}
                                                        className="h-1.5 w-1.5 rounded-full bg-gold"
                                                    />
                                                ),
                                            )}
                                        </span>
                                        <span className="flex flex-1 items-center justify-between px-4 py-3">
                                            <span className="font-sans text-sm font-semibold text-deep-purple">
                                                Bilhete {i + 1} ·{" "}
                                                {ticket.ticket_type_name}
                                                {dayLabel(
                                                    ticket.event_date,
                                                ) && (
                                                    <span className="text-deep-purple/50">
                                                        {" "}
                                                        (
                                                        {dayLabel(
                                                            ticket.event_date,
                                                        )}
                                                        )
                                                    </span>
                                                )}
                                            </span>
                                            <span className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple/50">
                                                PDF ↓
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}
