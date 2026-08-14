import { useState, type ChangeEvent } from "react";
import { postFormData } from "../../lib/auth";

type OrderSummary = {
    id: string;
    total_amount: string;
    proof_of_payment_uploaded: boolean;
};

/** Payment step for a pending order: a WhatsApp deep link (order ref/total/status-page URL pre-filled) or bank-transfer instructions, plus a proof-of-payment upload (spec.md FR-007-FR-009, FR-019-FR-021). */
export function PaymentMethodStep({
    order,
    whatsappNumber,
    bankDetails,
    onUploaded,
}: {
    order: OrderSummary;
    whatsappNumber: string | null;
    bankDetails: {
        accountName: string | null;
        accountNumber: string | null;
        bankName: string | null;
        branch: string | null;
        instructions: string | null;
    };
    onUploaded: () => void;
}) {
    const [method, setMethod] = useState<"whatsapp" | "bank">("whatsapp");
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const statusUrl = `${window.location.origin}/orders/${order.id}`;
    const message = `Hi! I'd like to complete payment for order ${order.id} (MZN ${order.total_amount}). ${statusUrl}`;
    const waLink = whatsappNumber
        ? `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`
        : null;

    async function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        setUploadError(null);

        try {
            const form = new FormData();
            form.append("file", file);
            await postFormData(`/orders/${order.id}/proof-of-payment`, form);
            onUploaded();
        } catch {
            setUploadError("Couldn't upload that file. Please try again.");
        } finally {
            setUploading(false);
        }
    }

    return (
        <div className="space-y-6">
            <div className="flex gap-2" role="group" aria-label="Payment method">
                <button
                    type="button"
                    aria-pressed={method === "whatsapp"}
                    onClick={() => setMethod("whatsapp")}
                    className={`flex-1 rounded-md border px-4 py-2 font-condensed text-sm font-semibold uppercase tracking-wide ${
                        method === "whatsapp"
                            ? "border-deep-purple bg-deep-purple text-white"
                            : "border-deep-purple/20 text-deep-purple"
                    }`}
                >
                    Pay via WhatsApp
                </button>
                <button
                    type="button"
                    aria-pressed={method === "bank"}
                    onClick={() => setMethod("bank")}
                    className={`flex-1 rounded-md border px-4 py-2 font-condensed text-sm font-semibold uppercase tracking-wide ${
                        method === "bank"
                            ? "border-deep-purple bg-deep-purple text-white"
                            : "border-deep-purple/20 text-deep-purple"
                    }`}
                >
                    Pay via bank transfer
                </button>
            </div>

            {method === "whatsapp" ? (
                <div className="rounded-md border border-deep-purple/10 p-4">
                    <p className="font-sans text-sm text-deep-purple/70">
                        Message us on WhatsApp to arrange payment — your order
                        reference and total are pre-filled.
                    </p>
                    {waLink ? (
                        <a
                            href={waLink}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 block w-full rounded-md bg-deep-purple px-4 py-3 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white hover:bg-gold hover:text-deep-purple"
                        >
                            Open WhatsApp
                        </a>
                    ) : (
                        <p className="mt-4 font-sans text-sm text-red-text">
                            WhatsApp checkout isn't configured yet — please
                            use bank transfer instead.
                        </p>
                    )}
                </div>
            ) : (
                <div className="rounded-md border border-deep-purple/10 p-4 font-sans text-sm text-deep-purple">
                    <p>
                        <span className="font-semibold">Account name:</span>{" "}
                        {bankDetails.accountName}
                    </p>
                    <p>
                        <span className="font-semibold">
                            Account number:
                        </span>{" "}
                        {bankDetails.accountNumber}
                    </p>
                    <p>
                        <span className="font-semibold">Bank:</span>{" "}
                        {bankDetails.bankName}
                    </p>
                    <p>
                        <span className="font-semibold">Branch:</span>{" "}
                        {bankDetails.branch}
                    </p>
                    <p className="mt-3">
                        <span className="font-semibold">Reference:</span>{" "}
                        {order.id}
                    </p>
                    <p>
                        <span className="font-semibold">Amount:</span> MZN{" "}
                        {order.total_amount}
                    </p>
                    {bankDetails.instructions && (
                        <p className="mt-3 whitespace-pre-line text-deep-purple/70">
                            {bankDetails.instructions}
                        </p>
                    )}
                </div>
            )}

            <div>
                <label
                    htmlFor="proof-of-payment"
                    className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple"
                >
                    {order.proof_of_payment_uploaded
                        ? "Replace proof of payment"
                        : "Upload proof of payment (optional)"}
                </label>
                <input
                    id="proof-of-payment"
                    type="file"
                    accept="image/jpeg,image/png,application/pdf"
                    disabled={uploading}
                    onChange={handleFileChange}
                    className="mt-2 block w-full font-sans text-sm text-deep-purple"
                />
                {order.proof_of_payment_uploaded && (
                    <p className="mt-1 font-sans text-xs text-deep-purple/50">
                        Received — a staff member will review it shortly.
                    </p>
                )}
                {uploadError && (
                    <p role="alert" className="mt-1 font-sans text-xs text-red-text">
                        {uploadError}
                    </p>
                )}
            </div>
        </div>
    );
}
