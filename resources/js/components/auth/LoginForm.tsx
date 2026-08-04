import { useState, type FormEvent } from "react";
import { Link } from "@tanstack/react-router";
import { AuthApiError, postJson } from "../../lib/auth";
import { FormField } from "./FormField";

type LoginResponse = { attendee: { id: string; name: string; email: string } };

export function LoginForm({
    verified,
    verificationFailed,
}: {
    verified: boolean;
    verificationFailed: boolean;
}) {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [emailError, setEmailError] = useState<string | undefined>();
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [unverified, setUnverified] = useState(false);
    const [resendState, setResendState] = useState<"idle" | "sending" | "sent">(
        "idle",
    );
    const [loggedInName, setLoggedInName] = useState<string | null>(null);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setEmailError(undefined);
        setFormError(null);
        setUnverified(false);

        try {
            const { attendee } = await postJson<LoginResponse>("/login", {
                email,
                password,
            });
            setLoggedInName(attendee.name);
        } catch (err) {
            if (err instanceof AuthApiError) {
                if (err.isUnverified) {
                    setUnverified(true);
                } else if (err.isThrottled) {
                    setFormError(
                        "Too many attempts. Please wait a minute before trying again.",
                    );
                } else if (err.body.errors) {
                    setEmailError(err.fieldError("email") ?? err.message);
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

    async function handleResend() {
        setResendState("sending");
        try {
            await postJson("/email/verification-notification", { email });
        } catch {
            // Resend is intentionally non-disclosing and best-effort — show "sent" either way.
        } finally {
            setResendState("sent");
        }
    }

    if (loggedInName) {
        return (
            <div role="status">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                    Signed in
                </p>
                <h2 className="mt-3 font-display text-3xl text-deep-purple">
                    Welcome back, {loggedInName}
                </h2>
                <p className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70">
                    Your session is active. You can close this tab or return to
                    the homepage.
                </p>
                <Link
                    to="/"
                    className="mt-6 inline-block font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                >
                    Go to homepage
                </Link>
            </div>
        );
    }

    return (
        <div>
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                Sign in
            </p>
            <h2 className="mt-3 font-display text-3xl text-deep-purple">
                Welcome back
            </h2>
            <p className="mt-3 font-sans text-base text-deep-purple/70">
                Enter your details to access your tickets.
            </p>

            {verified && (
                <div
                    role="status"
                    className="mt-6 rounded-md border border-gold/40 bg-gold/10 px-4 py-3 font-sans text-sm font-medium text-deep-purple"
                >
                    Your email is verified — sign in below.
                </div>
            )}
            {verificationFailed && (
                <div
                    role="alert"
                    className="mt-6 rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                >
                    That verification link is invalid or has expired. Sign in
                    and we'll help you request a new one.
                </div>
            )}

            <form noValidate onSubmit={handleSubmit} className="mt-8 space-y-5">
                {formError && (
                    <div
                        role="alert"
                        className="rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                    >
                        {formError}
                    </div>
                )}

                {unverified && (
                    <div
                        role="alert"
                        className="rounded-md border border-deep-purple/15 bg-deep-purple/[0.04] px-4 py-3 font-sans text-sm text-deep-purple"
                    >
                        <p className="font-medium">
                            Please verify your email address before logging in.
                        </p>
                        <button
                            type="button"
                            onClick={handleResend}
                            disabled={resendState !== "idle"}
                            className="mt-2 font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold disabled:opacity-60"
                        >
                            {resendState === "sent"
                                ? "Verification email sent"
                                : resendState === "sending"
                                  ? "Sending…"
                                  : "Resend verification email"}
                        </button>
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
                    error={emailError}
                />

                <FormField
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />

                <div className="text-right">
                    <Link
                        to="/auth/forgot-password"
                        className="font-sans text-sm font-medium text-deep-purple/70 underline decoration-gold decoration-2 underline-offset-2 hover:text-deep-purple"
                    >
                        Forgot your password?
                    </Link>
                </div>

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-md bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? "Signing in…" : "Sign in"}
                </button>
            </form>

            <p className="mt-6 text-center font-sans text-sm text-deep-purple/70">
                Need an account?{" "}
                <Link
                    to="/auth/register"
                    className="font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                >
                    Register
                </Link>
            </p>
        </div>
    );
}
