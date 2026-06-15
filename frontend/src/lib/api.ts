import type { ValidationErrorBody } from "@/types/api";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

/**
 * Error thrown for any non-2xx API response. Carries the HTTP status and,
 * for 422 responses, the field-level validation errors.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }

  /** First validation message for a field, if any. */
  fieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0];
  }
}

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
  token?: string | null;
}

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { body, token, headers, ...rest } = options;

  const isFormData = body instanceof FormData;

  const finalHeaders = new Headers(headers);
  finalHeaders.set("Accept", "application/json");
  // For FormData, let the browser set Content-Type (with the multipart boundary).
  if (body !== undefined && !isFormData) {
    finalHeaders.set("Content-Type", "application/json");
  }
  if (token) {
    finalHeaders.set("Authorization", `Bearer ${token}`);
  }

  const requestBody =
    body === undefined ? undefined : isFormData ? body : JSON.stringify(body);

  let response: Response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      ...rest,
      headers: finalHeaders,
      body: requestBody,
    });
  } catch {
    throw new ApiError("Could not reach the server. Please try again.", 0);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const errorBody = data as Partial<ValidationErrorBody> | null;
    throw new ApiError(
      errorBody?.message ?? "Something went wrong. Please try again.",
      response.status,
      errorBody?.errors,
    );
  }

  return data as T;
}
