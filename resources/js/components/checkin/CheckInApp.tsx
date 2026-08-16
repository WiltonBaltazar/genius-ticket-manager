import { useCallback, useEffect, useRef, useState } from "react";
import QrScanner from "qr-scanner";
import { AuthApiError, getJson, postJson } from "../../lib/auth";

type TicketResult = {
    id: string;
    status: "unused" | "checked_in" | "voided";
    attendee_name: string;
    ticket_type_name: string;
    event_name: string;
    event_date: string | null;
    wrong_day: boolean;
    checked_in_at: string | null;
    checked_in_by: string | null;
    order_reference: string;
};

const REJECTION_MESSAGE: Record<string, string> = {
    already_checked_in: "Este bilhete já foi utilizado.",
    voided: "Este bilhete foi anulado.",
    wrong_day: "Este bilhete é válido noutro dia do evento.",
};

function dayLabel(eventDate: string | null): string | null {
    if (!eventDate) return null;
    return new Date(`${eventDate}T00:00:00`).toLocaleDateString("pt", {
        day: "2-digit",
        month: "long",
    });
}

/** Door check-in: camera QR scan (primary) with a manual name/email/phone/order-reference search as fallback, per staff at a table without a working camera or an unreadable code. */
export function CheckInApp() {
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);

    const [cameraError, setCameraError] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState("");
    const [searchResults, setSearchResults] = useState<TicketResult[] | null>(null);
    const [selected, setSelected] = useState<TicketResult | null>(null);
    const [lookupError, setLookupError] = useState<string | null>(null);
    const [confirmError, setConfirmError] = useState<string | null>(null);
    const [confirmedTicket, setConfirmedTicket] = useState<TicketResult | null>(null);
    const [busy, setBusy] = useState(false);

    const resetToScanning = useCallback(() => {
        setSelected(null);
        setLookupError(null);
        setConfirmError(null);
        setConfirmedTicket(null);
        setSearchResults(null);
        setSearchQuery("");
        scannerRef.current?.start();
    }, []);

    const lookupQrCode = useCallback(async (qrCode: string) => {
        setBusy(true);
        setLookupError(null);
        try {
            const data = await getJson<{ tickets: TicketResult[] }>(
                `/admin/check-in/lookup?qr_code=${encodeURIComponent(qrCode)}`,
            );
            if (data.tickets.length === 0) {
                setLookupError("Bilhete não encontrado.");
                return;
            }
            setSelected(data.tickets[0]);
        } catch {
            setLookupError("Ocorreu um erro ao procurar o bilhete.");
        } finally {
            setBusy(false);
        }
    }, []);

    useEffect(() => {
        if (!videoRef.current) return;

        const scanner = new QrScanner(
            videoRef.current,
            (result) => {
                scanner.stop();
                lookupQrCode(result.data);
            },
            { preferredCamera: "environment", highlightScanRegion: true },
        );
        scannerRef.current = scanner;

        scanner.start().catch(() => {
            setCameraError(
                "Não foi possível aceder à câmara. Utilize a pesquisa manual abaixo.",
            );
        });

        return () => {
            scanner.stop();
            scanner.destroy();
        };
    }, [lookupQrCode]);

    async function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        if (!searchQuery.trim()) return;

        setBusy(true);
        setLookupError(null);
        try {
            const data = await getJson<{ tickets: TicketResult[] }>(
                `/admin/check-in/lookup?q=${encodeURIComponent(searchQuery.trim())}`,
            );
            setSearchResults(data.tickets);
            if (data.tickets.length === 0) {
                setLookupError("Nenhum bilhete corresponde a essa pesquisa.");
            }
        } catch {
            setLookupError("Ocorreu um erro ao procurar bilhetes.");
        } finally {
            setBusy(false);
        }
    }

    async function handleConfirm() {
        if (!selected) return;

        setBusy(true);
        setConfirmError(null);
        try {
            const data = await postJson<{ ticket: TicketResult }>(
                `/admin/check-in/tickets/${selected.id}/confirm`,
                {},
            );
            setConfirmedTicket(data.ticket);
        } catch (err) {
            // The confirm endpoint's 422 body shape (error/ticket) is specific to this page,
            // not part of AuthApiError's shared attendee-auth body type — cast locally.
            const body = err instanceof AuthApiError
                ? (err.body as { error?: string; ticket?: TicketResult })
                : null;

            if (body?.error) {
                setConfirmError(REJECTION_MESSAGE[body.error] ?? "Não foi possível confirmar a entrada.");
                if (body.ticket) {
                    setSelected(body.ticket);
                }
            } else {
                setConfirmError("Não foi possível confirmar a entrada.");
            }
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="min-h-screen bg-deep-purple font-sans text-white">
            <header className="px-5 pt-6 pb-4">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                    Check-in
                </p>
                <h1 className="mt-1 font-display text-2xl text-white">
                    Genius Behind the Brands
                </h1>
            </header>

            <main className="px-5 pb-12">
                {/* Always mounted (never conditionally unmounted) — qr-scanner is bound to this
                    exact element, and swapping it out on re-render would leave the scanner
                    pointing at a detached node with no way to resume the camera on reset. */}
                <div
                    className={`overflow-hidden rounded-2xl border border-white/10 bg-black ${
                        selected || confirmedTicket ? "hidden" : ""
                    }`}
                >
                    <video ref={videoRef} className="aspect-square w-full object-cover" />
                </div>

                {!selected && !confirmedTicket && (
                    <>
                        {cameraError && (
                            <p className="mt-3 font-sans text-sm text-white/70">{cameraError}</p>
                        )}

                        <form onSubmit={handleSearch} className="mt-6 space-y-3">
                            <label className="font-condensed text-xs font-semibold uppercase tracking-wide text-white/50">
                                Pesquisa manual — nome, email, telefone ou referência do pedido
                            </label>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="flex-1 rounded-full border border-white/20 bg-white/5 px-4 py-2.5 font-sans text-sm text-white placeholder:text-white/30 focus:border-gold focus:outline-none"
                                    placeholder="Ex: Maria, 01A00C30…"
                                />
                                <button
                                    type="submit"
                                    disabled={busy}
                                    className="shrink-0 rounded-full bg-gold px-5 py-2.5 font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple transition-colors hover:bg-gold-hover disabled:opacity-50"
                                >
                                    Procurar
                                </button>
                            </div>
                        </form>

                        {lookupError && (
                            <p className="mt-3 font-sans text-sm text-red-text bg-white/95 rounded-md px-3 py-2">
                                {lookupError}
                            </p>
                        )}

                        {searchResults && searchResults.length > 0 && (
                            <ul className="mt-4 space-y-2">
                                {searchResults.map((ticket) => (
                                    <li key={ticket.id}>
                                        <button
                                            type="button"
                                            onClick={() => setSelected(ticket)}
                                            className="w-full rounded-lg border border-white/15 bg-white/5 px-4 py-3 text-left transition-colors hover:border-gold"
                                        >
                                            <p className="font-sans text-sm font-semibold text-white">
                                                {ticket.attendee_name}
                                            </p>
                                            <p className="mt-0.5 font-sans text-xs text-white/60">
                                                {ticket.ticket_type_name} · Pedido #{ticket.order_reference}
                                            </p>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}

                {selected && !confirmedTicket && (
                    <div className="rounded-2xl bg-white px-5 py-6 text-deep-purple">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple/40">
                            Bilhete
                        </p>
                        <p className="mt-1 font-display text-2xl">{selected.attendee_name}</p>
                        <p className="mt-1 font-sans text-sm text-deep-purple/70">
                            {selected.ticket_type_name} · {selected.event_name}
                        </p>
                        {dayLabel(selected.event_date) && (
                            <p className="mt-1 font-sans text-sm text-deep-purple/70">
                                Dia: {dayLabel(selected.event_date)}
                            </p>
                        )}
                        <p className="mt-3 font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple/40">
                            Pedido #{selected.order_reference}
                        </p>

                        {selected.status !== "unused" && (
                            <p className="mt-4 rounded-md bg-red/10 px-3 py-2 font-sans text-sm font-medium text-red-text">
                                {REJECTION_MESSAGE[
                                    selected.status === "checked_in" ? "already_checked_in" : "voided"
                                ]}
                                {selected.status === "checked_in" && selected.checked_in_at && (
                                    <span className="block mt-1 text-xs text-red-text/80">
                                        Confirmado{selected.checked_in_by ? ` por ${selected.checked_in_by}` : ""}{" "}
                                        às {new Date(selected.checked_in_at).toLocaleTimeString("pt", { hour: "2-digit", minute: "2-digit" })}
                                    </span>
                                )}
                            </p>
                        )}

                        {selected.status === "unused" && selected.wrong_day && (
                            <p className="mt-4 rounded-md bg-red/10 px-3 py-2 font-sans text-sm font-medium text-red-text">
                                {REJECTION_MESSAGE.wrong_day}
                            </p>
                        )}

                        {confirmError && (
                            <p className="mt-4 rounded-md bg-red/10 px-3 py-2 font-sans text-sm font-medium text-red-text">
                                {confirmError}
                            </p>
                        )}

                        <div className="mt-6 flex gap-3">
                            <button
                                type="button"
                                onClick={resetToScanning}
                                className="flex-1 rounded-full border border-deep-purple/25 px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-deep-purple"
                            >
                                Cancelar
                            </button>
                            {selected.status === "unused" && !selected.wrong_day && (
                                <button
                                    type="button"
                                    onClick={handleConfirm}
                                    disabled={busy}
                                    className="flex-1 rounded-full bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple disabled:opacity-50"
                                >
                                    Confirmar entrada
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {confirmedTicket && (
                    <div className="rounded-2xl bg-gold px-5 py-8 text-center text-deep-purple">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em]">
                            Entrada confirmada
                        </p>
                        <p className="mt-3 font-display text-3xl">{confirmedTicket.attendee_name}</p>
                        <p className="mt-1 font-sans text-sm text-deep-purple/80">
                            {confirmedTicket.ticket_type_name}
                            {dayLabel(confirmedTicket.event_date) && ` · ${dayLabel(confirmedTicket.event_date)}`}
                        </p>
                        <button
                            type="button"
                            onClick={resetToScanning}
                            className="mt-6 w-full rounded-full bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white"
                        >
                            Escanear o próximo
                        </button>
                    </div>
                )}
            </main>
        </div>
    );
}
