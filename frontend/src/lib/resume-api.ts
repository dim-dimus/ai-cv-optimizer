import { apiFetch, ApiError } from "@/lib/api";
import type { Resume, ResourceEnvelope } from "@/types/api";

/**
 * Fetch the current user's resume, or null if they have none yet (404).
 */
export async function getResume(token: string): Promise<Resume | null> {
  try {
    const res = await apiFetch<ResourceEnvelope<Resume>>("/resume", { token });
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      return null;
    }
    throw err;
  }
}

export async function uploadResume(token: string, file: File): Promise<Resume> {
  const form = new FormData();
  form.append("file", file);
  const res = await apiFetch<ResourceEnvelope<Resume>>("/resume", {
    method: "POST",
    body: form,
    token,
  });
  return res.data;
}

export async function updateResumeText(token: string, parsedText: string): Promise<Resume> {
  const res = await apiFetch<ResourceEnvelope<Resume>>("/resume", {
    method: "PATCH",
    body: { parsed_text: parsedText },
    token,
  });
  return res.data;
}

export async function deleteResume(token: string): Promise<void> {
  await apiFetch<{ message: string }>("/resume", { method: "DELETE", token });
}
