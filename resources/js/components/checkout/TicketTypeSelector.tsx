import { useCart, type CartTicketType } from "../../lib/cart";

type TicketTypeSelectorProps = {
    eventId: string;
    eventSlug: string;
    ticketTypes: CartTicketType[];
};

/** Lists an event's ticket types with live price/availability and lets a visitor add them to the cart, never exceeding availableQuantity (spec.md FR-002, User Story 1). */
export function TicketTypeSelector({
    eventId,
    eventSlug,
    ticketTypes,
}: TicketTypeSelectorProps) {
    const cart = useCart();

    return (
        <ul className="mt-6 divide-y divide-deep-purple/10">
            {ticketTypes.map((ticketType) => {
                const inCart =
                    cart.items.find(
                        (item) => item.ticketType.id === ticketType.id,
                    )?.quantity ?? 0;
                const soldOut = ticketType.availableQuantity === 0;
                const atLimit = inCart >= ticketType.availableQuantity;

                return (
                    <li
                        key={ticketType.id}
                        className="flex items-center justify-between gap-4 py-4"
                    >
                        <div>
                            <p className="font-sans text-base font-semibold text-deep-purple">
                                {ticketType.name}
                            </p>
                            <p className="font-sans text-sm text-deep-purple/70">
                                MZN {ticketType.price}
                            </p>
                            {soldOut ? (
                                <p className="mt-1 font-condensed text-xs font-semibold uppercase tracking-wide text-red-text">
                                    Sold out
                                </p>
                            ) : (
                                <p className="mt-1 font-sans text-xs text-deep-purple/50">
                                    {ticketType.availableQuantity} remaining
                                </p>
                            )}
                        </div>

                        {!soldOut && (
                            <div className="flex items-center gap-3">
                                {inCart > 0 && (
                                    <>
                                        <button
                                            type="button"
                                            aria-label={`Remove one ${ticketType.name}`}
                                            onClick={() =>
                                                cart.setQuantity(
                                                    ticketType.id,
                                                    inCart - 1,
                                                )
                                            }
                                            className="h-8 w-8 rounded-full border border-deep-purple/20 font-sans text-deep-purple hover:border-gold"
                                        >
                                            −
                                        </button>
                                        <span className="w-6 text-center font-sans text-sm text-deep-purple">
                                            {inCart}
                                        </span>
                                    </>
                                )}
                                <button
                                    type="button"
                                    aria-label={`Add one ${ticketType.name}`}
                                    disabled={atLimit}
                                    onClick={() =>
                                        cart.add(eventId, eventSlug, ticketType)
                                    }
                                    className="h-8 w-8 rounded-full border border-deep-purple/20 font-sans text-deep-purple hover:border-gold disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    +
                                </button>
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
