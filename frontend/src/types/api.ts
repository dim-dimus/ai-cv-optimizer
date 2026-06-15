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

export interface Resume {
  id: number;
  original_filename: string;
  parsed_text: string | null;
  language: string;
  skills_synced_at: string | null;
  updated_at: string | null;
}

export type AnalysisStatus = "queued" | "processing" | "completed" | "failed";

export interface ScoreBreakdown {
  hard_skills: number;
  soft_skills: number;
  experience: number;
  education: number;
  keywords: number;
}

export interface MatchedRequirement {
  requirement: string;
  matched_skill: string | null;
  similarity: number | null;
}

export interface RequirementGap {
  requirement: string;
  category: string | null;
}

export interface Analysis {
  id: number;
  status: AnalysisStatus;
  error_message?: string | null;
  overall_score?: number;
  score_breakdown?: ScoreBreakdown;
  explanation?: string;
  completed_at?: string | null;
  matched?: MatchedRequirement[];
  gaps?: RequirementGap[];
}
