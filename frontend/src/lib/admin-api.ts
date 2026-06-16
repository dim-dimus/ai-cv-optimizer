import { apiFetch } from "@/lib/api";
import type {
  AdminUser,
  LlmLogEntry,
  PromptTemplate,
  ResourceEnvelope,
  UsageSummary,
} from "@/types/api";

export async function getPromptTemplates(token: string): Promise<PromptTemplate[]> {
  const res = await apiFetch<ResourceEnvelope<PromptTemplate[]>>("/admin/prompt-templates", { token });
  return res.data;
}

export interface PromptTemplateUpdate {
  content: string;
  model: string;
  max_tokens: number;
  temperature: number;
  is_active: boolean;
}

export async function updatePromptTemplate(
  token: string,
  slug: string,
  body: PromptTemplateUpdate,
): Promise<PromptTemplate> {
  const res = await apiFetch<ResourceEnvelope<PromptTemplate>>(`/admin/prompt-templates/${slug}`, {
    method: "PUT",
    body,
    token,
  });
  return res.data;
}

export async function getUsage(token: string): Promise<UsageSummary> {
  const res = await apiFetch<ResourceEnvelope<UsageSummary>>("/admin/usage", { token });
  return res.data;
}

export async function getUsers(token: string): Promise<AdminUser[]> {
  const res = await apiFetch<{ data: AdminUser[] }>("/admin/users", { token });
  return res.data;
}

export async function getLlmLogs(token: string): Promise<LlmLogEntry[]> {
  const res = await apiFetch<{ data: LlmLogEntry[] }>("/admin/llm-logs", { token });
  return res.data;
}
