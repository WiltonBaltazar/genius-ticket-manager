import { type InputHTMLAttributes } from "react";

type FormFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    name: string;
    error?: string;
    hint?: string;
};

/** Labeled input with inline error text, shared across the auth screens. */
export function FormField({
    label,
    name,
    error,
    hint,
    id,
    ...inputProps
}: FormFieldProps) {
    const fieldId = id ?? name;
    const errorId = `${fieldId}-error`;
    const hintId = `${fieldId}-hint`;
    const describedBy =
        [error ? errorId : null, hint ? hintId : null]
            .filter(Boolean)
            .join(" ") || undefined;

    return (
        <div>
            <label
                htmlFor={fieldId}
                className="font-condensed text-sm font-semibold uppercase tracking-wide text-deep-purple"
            >
                {label}
            </label>
            <input
                id={fieldId}
                name={name}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                className={`mt-2 block w-full rounded-md border px-3.5 py-2.5 font-sans text-base text-deep-purple shadow-sm outline-none transition-colors placeholder:text-deep-purple/35 focus:ring-2 focus:ring-gold focus:ring-offset-1 ${
                    error
                        ? "border-red"
                        : "border-deep-purple/20 focus:border-gold"
                }`}
                {...inputProps}
            />
            {hint && !error && (
                <p
                    id={hintId}
                    className="mt-1.5 font-sans text-sm text-deep-purple/70"
                >
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    className="mt-1.5 font-sans text-sm font-medium text-red-text"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
