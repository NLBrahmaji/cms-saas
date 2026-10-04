"use client";

import {
  authAlertClassName,
  authFieldClassName,
  authLabelClassName,
  authPrimaryButtonClassName,
} from "@/features/auth/components/auth-form-styles";
import { useAuth } from "@/features/auth/components/auth-provider";
import { AuthLoadingScreen } from "@/features/auth/components/auth-loading-screen";
import { authFormErrorMessage } from "@/features/auth/utils/auth-form-errors";
import { ApiError } from "@/lib/api/errors";
import type { ValidationErrors } from "@/lib/api/errors";
import { firstFieldError } from "@/lib/api/validation";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";

export function RegisterForm() {
  const router = useRouter();
  const { status, register } = useAuth();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors | undefined>();
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/accounts");
    }
  }, [status, router]);

  if (status === "loading") {
    return <AuthLoadingScreen />;
  }

  if (status === "authenticated") {
    return <AuthLoadingScreen message="Redirecting…" />;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setFieldErrors(undefined);

    if (password !== passwordConfirmation) {
      setError("Passwords do not match.");
      return;
    }

    setIsSubmitting(true);

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      router.replace("/accounts");
    } catch (submitError) {
      if (submitError instanceof ApiError && submitError.isValidationError()) {
        setFieldErrors(submitError.errors);
        setError(
          authFormErrorMessage(
            submitError,
            "Please correct the highlighted fields.",
          ),
        );
      } else {
        setError(
          authFormErrorMessage(
            submitError,
            "Unable to create your account. Please try again.",
          ),
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 dark:bg-zinc-950">
      <div className="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div className="mb-8 space-y-2">
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
            Create your SitePro account
          </h1>
          <p className="text-sm text-zinc-600 dark:text-zinc-400">
            Register to start managing your websites.
          </p>
        </div>

        <form className="space-y-5" onSubmit={handleSubmit}>
          <div className="space-y-2">
            <label className={authLabelClassName} htmlFor="name">
              Name
            </label>
            <input
              id="name"
              name="name"
              type="text"
              autoComplete="name"
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
              className={authFieldClassName}
            />
            {firstFieldError(fieldErrors, "name") ? (
              <p className="text-sm text-red-700 dark:text-red-300" role="alert">
                {firstFieldError(fieldErrors, "name")}
              </p>
            ) : null}
          </div>

          <div className="space-y-2">
            <label className={authLabelClassName} htmlFor="email">
              Email
            </label>
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className={authFieldClassName}
            />
            {firstFieldError(fieldErrors, "email") ? (
              <p className="text-sm text-red-700 dark:text-red-300" role="alert">
                {firstFieldError(fieldErrors, "email")}
              </p>
            ) : null}
          </div>

          <div className="space-y-2">
            <label className={authLabelClassName} htmlFor="password">
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="new-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className={authFieldClassName}
            />
            {firstFieldError(fieldErrors, "password") ? (
              <p className="text-sm text-red-700 dark:text-red-300" role="alert">
                {firstFieldError(fieldErrors, "password")}
              </p>
            ) : null}
          </div>

          <div className="space-y-2">
            <label className={authLabelClassName} htmlFor="password_confirmation">
              Confirm password
            </label>
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              autoComplete="new-password"
              required
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              className={authFieldClassName}
            />
          </div>

          {error ? (
            <p className={authAlertClassName} role="alert">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={isSubmitting}
            className={authPrimaryButtonClassName}
          >
            {isSubmitting ? "Creating account…" : "Create account"}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-zinc-600 dark:text-zinc-400">
          Already have an account?{" "}
          <Link
            href="/login"
            className="font-medium text-zinc-900 underline-offset-2 hover:underline dark:text-zinc-50"
          >
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
