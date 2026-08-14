type OrderPayload = {
    id: string;
    status: "pending" | "paid" | "expired";
    total_amount: string;
    items: {
        ticket_type_name: string;
        quantity: number;
        unit_price: string;
        subtotal: string;
    }[];
    tickets: { id: string; pdf_url: string }[];
};

/** Renders an order's pending/paid/expired states distinctly (spec.md FR-018, User Stories 3-5). */
export function OrderStatus({ order }: { order: OrderPayload }) {
    return (
        <div>
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                Order {order.id}
            </p>

            {order.status === "paid" && (
                <div
                    role="status"
                    className="mt-3 rounded-md border border-green-500/30 bg-green-50 px-4 py-3 font-sans text-sm font-medium text-green-800"
                >
                    Payment confirmed — your tickets are ready below.
                </div>
            )}

            {order.status === "pending" && (
                <div
                    role="status"
                    className="mt-3 rounded-md border border-gold/40 bg-gold/10 px-4 py-3 font-sans text-sm font-medium text-deep-purple"
                >
                    Awaiting payment. Complete one of the options below to
                    confirm your order.
                </div>
            )}

            {order.status === "expired" && (
                <div
                    role="status"
                    className="mt-3 rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                >
                    This order expired before payment was completed and its
                    tickets were released back for sale.
                </div>
            )}

            <ul className="mt-6 divide-y divide-deep-purple/10 rounded-md border border-deep-purple/10">
                {order.items.map((item, i) => (
                    <li
                        key={i}
                        className="flex items-center justify-between px-4 py-3 font-sans text-sm text-deep-purple"
                    >
                        <span>
                            {item.quantity} × {item.ticket_type_name}
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
                <div className="mt-6 space-y-2">
                    <p className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple">
                        Your tickets
                    </p>
                    {order.tickets.map((ticket, i) => (
                        <a
                            key={ticket.id}
                            href={ticket.pdf_url}
                            className="block rounded-md border border-deep-purple/20 px-4 py-3 font-sans text-sm font-semibold text-deep-purple hover:border-gold"
                        >
                            Download ticket {i + 1} (PDF)
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
