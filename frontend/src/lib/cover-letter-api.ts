import { apiFetch, ApiError } from "@/lib/api";
import type {
  CoverLetter,
  CoverLetterLength,
  CoverLetterTone,
  ResourceEnvelope,
} from "@/types/api";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export interface CoverLetterParams {
  tone: CoverLetterTone;
  length: CoverLetterLength;
  language: string;
}

export async function generateCoverLetter(
  token: string,
  analysisId: number,
  params: CoverLetterParams,
): Promise<CoverLetter> {
  const res = await apiFetch<ResourceEnvelope<CoverLetter>>(`/analyses/${analysisId}/cover-letter`, {
    method: "POST",
    body: params,
    token,
  });
  return res.data;
}

/** Current cover letter, or null if none has been generated yet (404). */
export async function getCoverLetter(token: string, analysisId: number): Promise<CoverLetter | null> {
  try {
    const res = await apiFetch<ResourceEnvelope<CoverLetter>>(
      `/analyses/${analysisId}/cover-letter`,
      { token },
    );
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      return null;
    }
    throw err;
  }
}

export async function updateCoverLetter(
  token: string,
  analysisId: number,
  content: string,
): Promise<CoverLetter> {
  const res = await apiFetch<ResourceEnvelope<CoverLetter>>(`/analyses/${analysisId}/cover-letter`, {
    method: "PATCH",
    body: { content },
    token,
  });
  return res.data;
}

/** Fetch the export binary and trigger a browser download. */
export async function downloadCoverLetter(
  token: string,
  analysisId: number,
  format: "pdf" | "docx",
): Promise<void> {
  const response = await fetch(
    `${BASE_URL}/analyses/${analysisId}/cover-letter/export?format=${format}`,
    { headers: { Authorization: `Bearer ${token}` } },
  );
  if (!response.ok) {
    throw new ApiError("Could not export the cover letter.", response.status);
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `cover-letter.${format}`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
