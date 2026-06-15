"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError } from "@/lib/api";
import {
  downloadCoverLetter,
  generateCoverLetter,
  getCoverLetter,
  updateCoverLetter,
} from "@/lib/cover-letter-api";
import { useAuth } from "@/lib/auth-context";
import type { CoverLetter, CoverLetterLength, CoverLetterTone } from "@/types/api";

const TONES: CoverLetterTone[] = ["professional", "friendly", "enthusiastic", "formal"];
const LENGTHS: CoverLetterLength[] = ["short", "medium", "long"];

function isRunning(cover: CoverLetter | null): boolean {
  return cover?.status === "queued" || cover?.status === "processing";
}

export function CoverLetterPanel({ analysisId }: { analysisId: number }) {
  const { token } = useAuth();

  const [cover, setCover] = useState<CoverLetter | null>(null);
  const [tone, setTone] = useState<CoverLetterTone>("professional");
  const [length, setLength] = useState<CoverLetterLength>("medium");
  const [content, setContent] = useState("");
  const [busy, setBusy] = useState<null | "generating" | "saving">(null);
  const [error, setError] = useState<string | null>(null);
  const loadedRef = useRef(false);

  const applyCover = useCallback((next: CoverLetter | null) => {
    setCover(next);
    if (next?.content != null) setContent(next.content);
    if (next?.tone) setTone(next.tone as CoverLetterTone);
    if (next?.length) setLength(next.length as CoverLetterLength);
  }, []);

  useEffect(() => {
    if (!token || loadedRef.current) return;
    loadedRef.current = true;
    let cancelled = false;
    void (async () => {
      try {
        const existing = await getCoverLetter(token, analysisId);
        if (!cancelled && existing) applyCover(existing);
      } catch {
        // Non-fatal.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token, analysisId, applyCover]);

  const status = cover?.status ?? null;
  useEffect(() => {
    if (!token || status === null || status === "completed" || status === "failed") return;
    let cancelled = false;
    const interval = setInterval(() => {
      void (async () => {
        try {
          const latest = await getCoverLetter(token, analysisId);
          if (!cancelled && latest) applyCover(latest);
        } catch {
          // Ignore transient errors.
        }
      })();
    }, 2000);
    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [token, analysisId, status, applyCover]);

  const generate = useCallback(async () => {
    if (!token) return;
    setError(null);
    setBusy("generating");
    try {
      applyCover(await generateCoverLetter(token, analysisId, { tone, length, language: "en" }));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not start generation.");
    } finally {
      setBusy(null);
    }
  }, [token, analysisId, tone, length, applyCover]);

  const save = useCallback(async () => {
    if (!token) return;
    setError(null);
    setBusy("saving");
    try {
      applyCover(await updateCoverLetter(token, analysisId, content));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not save your edits.");
    } finally {
      setBusy(null);
    }
  }, [token, analysisId, content, applyCover]);

  const download = useCallback(
    async (format: "pdf" | "docx") => {
      if (!token) return;
      setError(null);
      try {
        await downloadCoverLetter(token, analysisId, format);
      } catch {
        setError("Could not export the cover letter.");
      }
    },
    [token, analysisId],
  );

  const running = isRunning(cover) || busy === "generating";
  const completed = cover?.status === "completed";
  const dirty = completed && content !== (cover?.content ?? "");

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-xs text-gray-600">
          Tone
          <select
            value={tone}
            onChange={(e) => setTone(e.target.value as CoverLetterTone)}
            className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
          >
            {TONES.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs text-gray-600">
          Length
          <select
            value={length}
            onChange={(e) => setLength(e.target.value as CoverLetterLength)}
            className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
          >
            {LENGTHS.map((l) => (
              <option key={l} value={l}>
                {l}
              </option>
            ))}
          </select>
        </label>
        <button
          onClick={() => void generate()}
          disabled={running}
          className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
        >
          {running ? "Generating…" : cover ? "Regenerate" : "Generate cover letter"}
        </button>
      </div>

      {error ? <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      {cover?.status === "failed" ? (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          {cover.error_message ?? "Generation failed."}
        </p>
      ) : null}

      {completed ? (
        <div className="flex flex-col gap-3">
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={14}
            className="w-full rounded-md border border-gray-300 p-3 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900"
          />
          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={() => void save()}
              disabled={busy !== null || !dirty}
              className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
            >
              {busy === "saving" ? "Saving…" : "Save edits"}
            </button>
            <button
              onClick={() => void download("pdf")}
              className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Export PDF
            </button>
            <button
              onClick={() => void download("docx")}
              className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Export DOCX
            </button>
            {dirty ? <span className="text-xs text-gray-500">Unsaved changes</span> : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
