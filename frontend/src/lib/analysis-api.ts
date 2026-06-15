import { apiFetch, ApiError } from "@/lib/api";
import type { Analysis, ResourceEnvelope } from "@/types/api";

export async function createAnalysis(token: string, jobDescription: string): Promise<Analysis> {
  const res = await apiFetch<ResourceEnvelope<Analysis>>("/analyses", {
    method: "POST",
    body: { job_description: jobDescription },
    token,
  });
  return res.data;
}

export async function getAnalysis(token: string, id: number): Promise<Analysis> {
  const res = await apiFetch<ResourceEnvelope<Analysis>>(`/analyses/${id}`, { token });
  return res.data;
}

/** Most recent analysis, or null if the user has none yet (404). */
export async function getLatestAnalysis(token: string): Promise<Analysis | null> {
  try {
    const res = await apiFetch<ResourceEnvelope<Analysis>>("/analyses/latest", { token });
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      return null;
    }
    throw err;
  }
}
