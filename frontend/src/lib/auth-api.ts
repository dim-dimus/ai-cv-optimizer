import { apiFetch } from "@/lib/api";
import type {
  AuthResponse,
  LoginPayload,
  RegisterPayload,
  ResourceEnvelope,
  User,
} from "@/types/api";

export function register(payload: RegisterPayload): Promise<AuthResponse> {
  return apiFetch<AuthResponse>("/auth/register", { method: "POST", body: payload });
}

export function login(payload: LoginPayload): Promise<AuthResponse> {
  return apiFetch<AuthResponse>("/auth/login", { method: "POST", body: payload });
}

export function logout(token: string): Promise<void> {
  return apiFetch<void>("/auth/logout", { method: "POST", token });
}

export async function fetchMe(token: string): Promise<User> {
  const res = await apiFetch<ResourceEnvelope<User>>("/auth/me", { token });
  return res.data;
}

export function deleteAccount(token: string): Promise<void> {
  return apiFetch<void>("/auth/account", { method: "DELETE", token });
}
