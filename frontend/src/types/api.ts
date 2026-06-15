// Shared API contract types. These mirror the Laravel API responses (docs/API.md)
// and must be kept in sync with the backend API Resources.

export type UserRole = "user" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  created_at: string | null;
}

export interface AuthResponse {
  user: User;
  token: string;
}

// Laravel JsonResource wraps single resources in a `data` envelope.
export interface ResourceEnvelope<T> {
  data: T;
}

export interface ValidationErrorBody {
  message: string;
  errors: Record<string, string[]>;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}
