import { ApiError } from "@/lib/api/errors";

export function authFormErrorMessage(
  error: unknown,
  validationFallback: string,
): string {
  if (error instanceof ApiError) {
    if (error.isValidationError()) {
      return validationFallback;
    }

    if (error.status === 0) {
      return "Unable to reach the server. Check that the API is running.";
    }

    if (error.status === 419) {
      return "Your session could not be verified. Refresh the page and try again.";
    }

    if (error.status === 401) {
      return "Your session has expired. Sign in again.";
    }

    if (error.status === 403) {
      return "You do not have access to perform this action.";
    }

    return "Something went wrong. Please try again.";
  }

  if (error instanceof Error && error.message.includes("NEXT_PUBLIC_API_URL")) {
    return error.message;
  }

  return "Something went wrong. Please try again.";
}
