import type { ValidationErrors } from "@/lib/api/errors";

export function firstFieldError(
  errors: ValidationErrors | undefined,
  field: string,
): string | null {
  const messages = errors?.[field];

  if (!messages?.length) {
    return null;
  }

  return messages[0];
}
