import { useState, type FormEvent } from "react";
import { Link } from "@tanstack/react-router";
import { AuthApiError, postJson } from "../../lib/auth";
import { FormField } from "./FormField";

type RegisterResponse = { id: string; name: string; email: string };

const initialForm = {
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
};

export function RegisterForm() {
    const [form, setForm] = useState(initialForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [registeredEmail, setRegisteredEmail] = useState<string | null>(null);

    function update(field: keyof typeof form) {
        return (e: React.ChangeEvent<HTMLInputElement>) => {
            setForm((prev) => ({ ...prev, [field]: e.target.value }));
        };
    }

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        try {
            await postJson<RegisterResponse>("/register", {
                name: form.name,
                email: form.email,
                phone: form.phone || null,
                password: form.password,
                password_confirmation: form.password_confirmation,
            });

            setRegisteredEmail(form.email);
        } catch (err) {
            if (err instanceof AuthApiError) {
                if (err.isThrottled) {
                    setFormError(
                        "Too many attempts. Please wait a minute before trying again.",
                    );
                } else if (err.body.errors) {
                    const next: Record<string, string> = {};
                    for (const field of [
                        "name",
                        "email",
                        "phone",
                        "password",
                    ] as const) {
                        const message = err.fieldError(field);
                        if (message) next[field] = message;
                    }
                    setErrors(next);
                    if (Object.keys(next).length === 0) {
                        setFormError(err.message);
                    }
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

    if (registeredEmail) {
        return (
            <div role="status">
                <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                    Almost there
                </p>
                <h2 className="mt-3 font-display text-3xl text-deep-purple">
                    Check your inbox
                </h2>
                <p className="mt-4 font-sans text-base leading-relaxed text-deep-purple/70">
                    We sent a verification link to{" "}
                    <span className="font-semibold text-deep-purple">
                        {registeredEmail}
                    </span>
                    . Open it to confirm your address — you'll need to verify
                    before you can log in.
                </p>
                <p className="mt-6 font-sans text-sm text-deep-purple/70">
                    Didn't get it? Check spam, or head to{" "}
                    <Link
                        to="/auth/login"
                        className="font-semibold text-deep-purple underline decoration-gold decoration-2 underline-offset-2 hover:text-gold"
                    >
                        sign in
                    </Link>{" "}
                    and request a new link from there.
                </p>
            </div>
        );
    }

    return (
        <div>
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                Create your account
            </p>
            <h2 className="mt-3 font-display text-3xl text-deep-purple">
                Get your pass
            </h2>
            <p className="mt-3 font-sans text-base text-deep-purple/70">
                Register below to start booking tickets for this year's event.
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
                    label="Full name"
                    name="name"
                    type="text"
                    autoComplete="name"
                    required
                    value={form.name}
                    onChange={update("name")}
                    error={errors.name}
                />

                <FormField
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    value={form.email}
                    onChange={update("email")}
                    error={errors.email}
                />

                <FormField
                    label="Phone (optional)"
                    name="phone"
                    type="tel"
                    autoComplete="tel"
                    value={form.phone}
                    onChange={update("phone")}
                    error={errors.phone}
                />

                <FormField
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.password}
                    onChange={update("password")}
                    error={errors.password}
                    hint="At least 12 characters, with upper and lower case, a number, and a symbol."
                />

                <FormField
                    label="Confirm password"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.password_confirmation}
                    onChange={update("password_confirmation")}
                />

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-md bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? "Creating your account…" : "Create account"}
                </button>
            </form>

            <p className="mt-6 text-center font-sans text-sm text-deep-purple/70">
                Already registered?{" "}
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
