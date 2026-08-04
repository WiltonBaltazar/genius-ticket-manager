import { useState, type FormEvent } from "react";
import { Link } from "@tanstack/react-router";
import { AuthApiError, postJson } from "../../lib/auth";
import { FormField } from "./FormField";

export function ForgotPasswordForm({
    token,
    email,
}: {
    token?: string;
    email?: string;
}) {
    return token && email ? (
        <ResetPasswordStep token={token} email={email} />
    ) : (
        <RequestLinkStep />
    );
}

function RequestLinkStep() {
    const [email, setEmail] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [sent, setSent] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setFormError(null);

        try {
            await postJson("/forgot-password", { email });
            setSent(true);
        } catch (err) {
            if (err instanceof AuthApiError && err.isThrottled) {
                setFormError(
                    "Too many attempts. Please wait a minute before trying again.",
                );
            } else {
                // Non-disclosing endpoint — any other failure still resolves as "sent" to the user.
                setSent(true);
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (sent) {
        return (
            <div role="status">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                    Check your inbox
                </p>
                <h2 className="mt-3 font-display text-3xl text-deep-purple">
                    Link on its way
                </h2>
                <p className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70">
                    If that email is registered, we've sent a link to reset your
                    password. It's valid for one hour.
                </p>
                <Link
                    to="/auth/login"
                    className="mt-6 inline-block font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                >
                    Back to sign in
                </Link>
            </div>
        );
    }

    return (
        <div>
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                Reset your password
            </p>
            <h2 className="mt-3 font-display text-3xl text-deep-purple">
                Forgot your password?
            </h2>
            <p className="mt-3 font-sans text-base text-deep-purple/70">
                Enter the email on your account and we'll send you a link to get
                back in.
            </p>

            <form noValidate onSubmit={handleSubmit} className="mt-8 space-y-5">
                {formError && (
                    <div
                        role="alert"
                        className="rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                    >
                        {formError}
                    </div>
                )}

                <FormField
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-md bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? "Sending…" : "Send reset link"}
                </button>
            </form>

            <p className="mt-6 text-center font-sans text-sm text-deep-purple/70">
                Remembered it?{" "}
                <Link
                    to="/auth/login"
                    className="font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                >
                    Sign in
                </Link>
            </p>
        </div>
    );
}

function ResetPasswordStep({ token, email }: { token: string; email: string }) {
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [passwordError, setPasswordError] = useState<string | undefined>();
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [done, setDone] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setPasswordError(undefined);
        setFormError(null);

        try {
            await postJson("/reset-password", {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation,
            });
            setDone(true);
        } catch (err) {
            if (err instanceof AuthApiError && err.body.errors) {
                const message =
                    err.fieldError("password") ?? err.fieldError("token");
                if (message) {
                    setPasswordError(message);
                } else {
                    setFormError(err.message);
                }
            } else {
                setFormError("Something went wrong. Please try again.");
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (done) {
        return (
            <div role="status">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                    Password updated
                </p>
                <h2 className="mt-3 font-display text-3xl text-deep-purple">
                    You're all set
                </h2>
                <p className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70">
                    Your password has been reset and every other active session
                    has been signed out. Please log in again.
                </p>
                <Link
                    to="/auth/login"
                    className="mt-6 inline-block font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                >
                    Sign in
                </Link>
            </div>
        );
    }

    return (
        <div>
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                Set a new password
            </p>
            <h2 className="mt-3 font-display text-3xl text-deep-purple">
                Choose a new password
            </h2>
            <p className="mt-3 font-sans text-base text-deep-purple/70">
                Resetting for {email}.
            </p>

            <form noValidate onSubmit={handleSubmit} className="mt-8 space-y-5">
                {formError && (
                    <div
                        role="alert"
                        className="rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                    >
                        {formError}
                    </div>
                )}

                <FormField
                    label="New password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    error={passwordError}
                    hint="At least 12 characters, with upper and lower case, a number, and a symbol."
                />

                <FormField
                    label="Confirm new password"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={passwordConfirmation}
                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                />

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-md bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? "Resetting…" : "Reset password"}
                </button>
            </form>
        </div>
    );
}
