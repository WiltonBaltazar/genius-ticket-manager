import { useState } from "react";
import { useCart, type CartTicketType } from "../../lib/cart";

type TicketTypeSelectorProps = {
    eventId: string;
    eventSlug: string;
    event: { startDate: string; endDate: string | null };
    ticketTypes: (Omit<CartTicketType, "eventDate"> & {
        description?: string | null;
    })[];
};

/** YYYY-MM-DD for a Date's local calendar day — not toISOString(), which converts to UTC first and lands on the wrong day for any positive UTC offset (e.g. Mozambique, UTC+2) once local midnight rolls back across the UTC date boundary. */
function toLocalIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

/** ISO (YYYY-MM-DD) calendar days the event spans, inclusive. A single-day event yields exactly one entry. */
function eventDays(startDate: string, endDate: string | null): string[] {
    const start = new Date(startDate);
    const startDay = new Date(
        start.getFullYear(),
        start.getMonth(),
        start.getDate(),
    );
    const end = endDate ? new Date(endDate) : startDay;
    const endDay = new Date(end.getFullYear(), end.getMonth(), end.getDate());

    const days: string[] = [];
    for (
        const cursor = new Date(startDay);
        cursor <= endDay;
        cursor.setDate(cursor.getDate() + 1)
    ) {
        days.push(toLocalIsoDate(cursor));
    }

    return days;
}

function formatDayLabel(isoDate: string): string {
    return new Date(`${isoDate}T00:00:00`).toLocaleDateString("pt", {
        day: "2-digit",
        month: "short",
    });
}

function dividedPrice(price: string, dayCount: number): string {
    return (Number(price) / dayCount).toFixed(2);
}

/** Lists an event's ticket types with live price/availability and lets a visitor add them to the cart, never exceeding availableQuantity (spec.md FR-002, User Story 1). For a multi-day event, a buyer can additionally pick one specific day per ticket type at price ÷ number of days — full pass and single-day selections share the same availableQuantity pool. */
export function TicketTypeSelector({
    eventId,
    eventSlug,
    event,
    ticketTypes,
}: TicketTypeSelectorProps) {
    const cart = useCart();
    const days = eventDays(event.startDate, event.endDate);
    const isMultiDay = days.length > 1;

    // Which day (or null = full pass) is currently selected per ticket type, defaulting to the full pass.
    const [selectedDay, setSelectedDay] = useState<Record<string, string | null>>({});

    return (
        <ul className="mt-6 space-y-3">
            {ticketTypes.map((ticketType) => {
                const selectedDate = selectedDay[ticketType.id] ?? null;
                const variant: CartTicketType = {
                    id: ticketType.id,
                    name: ticketType.name,
                    price: selectedDate
                        ? dividedPrice(ticketType.price, days.length)
                        : ticketType.price,
                    availableQuantity: ticketType.availableQuantity,
                    eventDate: selectedDate,
                };

                const inCartForVariant =
                    cart.items.find(
                        (item) =>
                            item.ticketType.id === ticketType.id &&
                            item.ticketType.eventDate === selectedDate,
                    )?.quantity ?? 0;
                const inCartForType = cart.items
                    .filter((item) => item.ticketType.id === ticketType.id)
                    .reduce((sum, item) => sum + item.quantity, 0);

                const soldOut = ticketType.availableQuantity === 0;
                const atLimit = inCartForType >= ticketType.availableQuantity;
                const selected = inCartForVariant > 0;

                return (
                    <li
                        key={ticketType.id}
                        className={`flex overflow-hidden rounded-lg border transition-colors ${
                            soldOut
                                ? "border-deep-purple/10 opacity-50"
                                : selected
                                  ? "border-deep-purple/30"
                                  : "border-deep-purple/15"
                        }`}
                    >
                        {/* Torn-edge stub: a dashed tear line, echoed at ticket-download scale on the order page. */}
                        <div
                            aria-hidden="true"
                            className="flex w-6 shrink-0 flex-col items-center justify-center gap-1.5 border-r border-dashed border-deep-purple/20 bg-deep-purple/[0.03] py-4"
                        >
                            {Array.from({ length: 4 }).map((_, i) => (
                                <span
                                    key={i}
                                    className={`h-1.5 w-1.5 rounded-full transition-colors ${
                                        selected
                                            ? "bg-gold"
                                            : "bg-deep-purple/15"
                                    }`}
                                />
                            ))}
                        </div>

                        <div className="flex flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-3 px-5 py-4">
                            <div>
                                <p className="font-sans text-base font-semibold text-deep-purple">
                                    {ticketType.name}
                                </p>
                                {ticketType.description && (
                                    <p className="mt-0.5 max-w-xs font-sans text-sm text-deep-purple/60">
                                        {ticketType.description}
                                    </p>
                                )}
                                {(soldOut || ticketType.availableQuantity <= 10) && (
                                    <p className="mt-1.5 font-condensed text-xs font-semibold uppercase tracking-wide">
                                        {soldOut ? (
                                            <span className="text-red-text">
                                                Esgotado
                                            </span>
                                        ) : (
                                            <span className="text-deep-purple/40">
                                                {ticketType.availableQuantity}{" "}
                                                restantes
                                            </span>
                                        )}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                <p className="font-display text-lg text-deep-purple">
                                    MZN {variant.price}
                                </p>

                                {!soldOut &&
                                    (selected ? (
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                aria-label={`Remover um ${ticketType.name}`}
                                                onClick={() =>
                                                    cart.setQuantity(
                                                        ticketType.id,
                                                        inCartForVariant - 1,
                                                        selectedDate,
                                                    )
                                                }
                                                className="h-8 w-8 rounded-full border border-deep-purple/25 font-sans text-deep-purple transition-colors hover:border-gold hover:text-gold-hover"
                                            >
                                                −
                                            </button>
                                            <input
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                max={ticketType.availableQuantity}
                                                value={inCartForVariant}
                                                onChange={(e) => {
                                                    const next = Number(
                                                        e.target.value,
                                                    );
                                                    if (Number.isNaN(next))
                                                        return;
                                                    cart.setQuantity(
                                                        ticketType.id,
                                                        next,
                                                        selectedDate,
                                                    );
                                                }}
                                                onFocus={(e) =>
                                                    e.target.select()
                                                }
                                                aria-label={`Quantidade de ${ticketType.name}`}
                                                className="w-10 rounded border border-deep-purple/25 bg-transparent text-center font-sans text-sm font-semibold text-deep-purple [appearance:textfield] focus:border-gold focus:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                            <button
                                                type="button"
                                                aria-label={`Adicionar um ${ticketType.name}`}
                                                disabled={atLimit}
                                                onClick={() =>
                                                    cart.add(
                                                        eventId,
                                                        eventSlug,
                                                        variant,
                                                    )
                                                }
                                                className="h-8 w-8 rounded-full border border-deep-purple/25 font-sans text-deep-purple transition-colors hover:border-gold hover:text-gold-hover disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                +
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                cart.add(
                                                    eventId,
                                                    eventSlug,
                                                    variant,
                                                )
                                            }
                                            className="rounded-full border border-deep-purple px-4 py-1.5 font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple transition-colors hover:bg-deep-purple hover:text-white"
                                        >
                                            Adicionar
                                        </button>
                                    ))}
                            </div>

                            {isMultiDay && !soldOut && (
                                <div
                                    // rounded-2xl, not rounded-full: this row can wrap to multiple
                                    // lines on a narrow phone (many days), and a fully-rounded
                                    // radius distorts into an oversized blob once the container is
                                    // taller than one pill row.
                                    className="flex w-full flex-wrap gap-1 rounded-2xl bg-deep-purple/5 p-1"
                                    role="group"
                                    aria-label={`Dia para ${ticketType.name}`}
                                >
                                    <button
                                        type="button"
                                        aria-pressed={selectedDate === null}
                                        onClick={() =>
                                            setSelectedDay((prev) => ({
                                                ...prev,
                                                [ticketType.id]: null,
                                            }))
                                        }
                                        className={`rounded-full px-3 py-1 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                                            selectedDate === null
                                                ? "bg-white text-deep-purple shadow-sm"
                                                : "text-deep-purple/50"
                                        }`}
                                    >
                                        Todos os dias
                                    </button>
                                    {days.map((day, index) => (
                                        <button
                                            key={day}
                                            type="button"
                                            aria-pressed={selectedDate === day}
                                            onClick={() =>
                                                setSelectedDay((prev) => ({
                                                    ...prev,
                                                    [ticketType.id]: day,
                                                }))
                                            }
                                            className={`rounded-full px-3 py-1 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                                                selectedDate === day
                                                    ? "bg-white text-deep-purple shadow-sm"
                                                    : "text-deep-purple/50"
                                            }`}
                                        >
                                            Dia {index + 1} ·{" "}
                                            {formatDayLabel(day)}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
