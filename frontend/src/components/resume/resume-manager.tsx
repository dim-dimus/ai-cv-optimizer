"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { deleteResume, getResume, updateResumeText, uploadResume } from "@/lib/resume-api";
import type { Resume } from "@/types/api";

const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ["pdf", "docx"];

function validateFile(file: File): string | null {
  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
  if (!ALLOWED_EXTENSIONS.includes(extension)) {
    return "Please choose a PDF or DOCX file.";
  }
  if (file.size > MAX_BYTES) {
    return "The file must be 5 MB or smaller.";
  }
  return null;
}

export function ResumeManager() {
  const { token } = useAuth();

  const [resume, setResume] = useState<Resume | null>(null);
  const [text, setText] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<null | "uploading" | "saving" | "deleting">(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const applyResume = useCallback((next: Resume | null) => {
    setResume(next);
    setText(next?.parsed_text ?? "");
  }, []);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;

    void (async () => {
      try {
        const current = await getResume(token);
        if (!cancelled) applyResume(current);
      } catch {
        if (!cancelled) setError("Could not load your resume.");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token, applyResume]);

  // While skills are still syncing in the background, poll for status and update
  // "Analysing… → ready" without a refresh. Only metadata is refreshed here, never
  // the textarea, so any unsaved edits are preserved.
  const syncedAt = resume?.skills_synced_at ?? null;
  const resumeId = resume?.id ?? null;
  useEffect(() => {
    if (!token || resumeId === null || syncedAt !== null) return;
    let cancelled = false;

    const interval = setInterval(() => {
      void (async () => {
        try {
          const latest = await getResume(token);
          if (!cancelled && latest) setResume(latest);
        } catch {
          // Ignore transient polling errors; the next tick retries.
        }
      })();
    }, 2500);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [token, resumeId, syncedAt]);

  async function handleUpload(file: File) {
    if (!token) return;
    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    setError(null);
    setNotice(null);
    setBusy("uploading");
    try {
      const uploaded = await uploadResume(token, file);
      applyResume(uploaded);
      setNotice("Resume uploaded. Review the extracted text below and edit if needed.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Upload failed. Please try again.");
    } finally {
      setBusy(null);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  }

  async function handleSave() {
    if (!token) return;
    setError(null);
    setNotice(null);
    setBusy("saving");
    try {
      const updated = await updateResumeText(token, text);
      applyResume(updated);
      setNotice("Saved.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not save. Please try again.");
    } finally {
      setBusy(null);
    }
  }

  async function handleDelete() {
    if (!token) return;
    setError(null);
    setNotice(null);
    setBusy("deleting");
    try {
      await deleteResume(token);
      applyResume(null);
      setNotice("Resume deleted.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not delete. Please try again.");
    } finally {
      setBusy(null);
    }
  }

  if (loading) {
    return <p className="text-sm text-gray-500">Loading your resume…</p>;
  }

  const dirty = resume !== null && text !== (resume.parsed_text ?? "");

  return (
    <div className="flex flex-col gap-4">
      {error ? (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      ) : null}
      {notice ? (
        <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{notice}</p>
      ) : null}

      <div className="flex items-center gap-3">
        <input
          ref={fileInputRef}
          type="file"
          accept=".pdf,.docx"
          disabled={busy !== null}
          onChange={(e) => {
            const file = e.target.files?.[0];
            if (file) void handleUpload(file);
          }}
          className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-gray-800"
        />
        {busy === "uploading" ? (
          <span className="text-sm text-gray-500">Uploading and parsing…</span>
        ) : null}
      </div>

      {resume ? (
        <div className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p className="text-sm font-medium text-gray-900">{resume.original_filename}</p>
              <p className="text-xs text-gray-500">
                {resume.skills_synced_at
                  ? "Skills analysed and ready."
                  : "Analysing skills in the background…"}
              </p>
            </div>
            <button
              onClick={() => void handleDelete()}
              disabled={busy !== null}
              className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              {busy === "deleting" ? "Deleting…" : "Delete"}
            </button>
          </div>

          <label htmlFor="parsed_text" className="text-sm font-medium text-gray-700">
            Extracted text
          </label>
          <textarea
            id="parsed_text"
            value={text}
            onChange={(e) => setText(e.target.value)}
            rows={16}
            className="w-full rounded-md border border-gray-300 p-3 font-mono text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900"
          />
          <div className="flex items-center gap-3">
            <button
              onClick={() => void handleSave()}
              disabled={busy !== null || !dirty}
              className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
            >
              {busy === "saving" ? "Saving…" : "Save changes"}
            </button>
            <span className="text-xs text-gray-500">
              {dirty ? "Unsaved changes" : "Saved — edit the text above to make changes"}
            </span>
          </div>
        </div>
      ) : (
        <p className="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
          Upload your resume (PDF or DOCX, up to 5 MB) to get started.
        </p>
      )}
    </div>
  );
}
