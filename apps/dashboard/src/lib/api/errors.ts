export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  readonly status: number;

  readonly errors?: ValidationErrors;

  constructor(message: string, status: number, errors?: ValidationErrors) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }

  isUnauthorized(): boolean {
    return this.status === 401;
  }

  isForbidden(): boolean {
    return this.status === 403;
  }

  isNotFound(): boolean {
    return this.status === 404;
  }

  isValidationError(): boolean {
    return this.status === 422;
  }
}
