import { apiFetch } from "@/lib/api";
import type { BulletStatus, BulletSuggestion, ResourceEnvelope } from "@/types/api";

export async function generateBullets(token: string, analysisId: number): Promise<void> {
  await apiFetch<{ message: string }>(`/analyses/${analysisId}/bullets`, {
    method: "POST",
    body: {},
    token,
  });
}

export async function getBullets(token: string, analysisId: number): Promise<BulletSuggestion[]> {
  const res = await apiFetch<ResourceEnvelope<BulletSuggestion[]>>(
    `/analyses/${analysisId}/bullets`,
    { token },
  );
  return res.data;
}

export async function updateBullet(
  token: string,
  bulletId: number,
  status: BulletStatus,
  editedText?: string,
): Promise<BulletSuggestion> {
  const res = await apiFetch<ResourceEnvelope<BulletSuggestion>>(`/bullets/${bulletId}`, {
    method: "PATCH",
    body: { status, edited_text: editedText },
    token,
  });
  return res.data;
}
