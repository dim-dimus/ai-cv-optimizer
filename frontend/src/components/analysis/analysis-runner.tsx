"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError } from "@/lib/api";
import { createAnalysis, getAnalysis, getLatestAnalysis } from "@/lib/analysis-api";
import { useAuth } from "@/lib/auth-context";
import type { Analysis } from "@/types/api";
import { AnalysisResult } from "@/components/analysis/analysis-result";

const MIN_JD_LENGTH = 30;

function isRunning(analysis: Analysis | null): boolean {
  return analysis?.status === "queued" || analysis?.status === "processing";
}

export function AnalysisRunner() {
  const { token } = useAuth();

  const [jobDescription, setJobDescription] = useState("");
  const [analysis, setAnalysis] = useState<Analysis | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const loadedRef = useRef(false);

  // Load the most recent analysis once, so a completed result (or an in-flight
  // run) is restored on revisit.
  useEffect(() => {
    if (!token || loadedRef.current) return;
    loadedRef.current = true;

    let cancelled = false;
    void (async () => {
      try {
        const latest = await getLatestAnalysis(token);
        if (!cancelled && latest) setAnalysis(latest);
      } catch {
        // Non-fatal: the user can still start a new analysis.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token]);

  // Poll while the current analysis is queued/processing.
  const analysisId = analysis?.id ?? null;
  const status = analysis?.status ?? null;
  useEffect(() => {
    if (!token || analysisId === null || status === "completed" || status === "failed") return;

    let cancelled = false;
    const interval = setInterval(() => {
      void (async () => {
        try {
          const latest = await getAnalysis(token, analysisId);
          if (!cancelled) setAnalysis(latest);
        } catch {
          // Ignore transient errors; the next tick retries.
        }
      })();
    }, 2000);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [token, analysisId, status]);

  const run = useCallback(async () => {
    if (!token) return;
    setError(null);
    setSubmitting(true);
    try {
      setAnalysis(await createAnalysis(token, jobDescription));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not start the analysis. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }, [token, jobDescription]);

  const running = isRunning(analysis);
  const canRun = !submitting && !running && jobDescription.trim().length >= MIN_JD_LENGTH;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-2">
        <label htmlFor="job_description" className="text-sm font-medium text-gray-700">
          Job description
        </label>
        <textarea
          id="job_description"
          value={jobDescription}
          onChange={(e) => setJobDescription(e.target.value)}
          rows={8}
          placeholder="Paste the job description here…"
          className="w-full rounded-md border border-gray-300 p-3 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900"
        />
      </div>

      {error ? (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      ) : null}

      <div className="flex items-center gap-3">
        <button
          onClick={() => void run()}
          disabled={!canRun}
          className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
        >
          {submitting ? "Starting…" : running ? "Analysing…" : "Run analysis"}
        </button>
        {jobDescription.trim().length > 0 && jobDescription.trim().length < MIN_JD_LENGTH ? (
          <span className="text-xs text-gray-500">Paste a longer job description to continue.</span>
        ) : null}
      </div>

      {analysis ? <AnalysisResult analysis={analysis} /> : null}
    </div>
  );
}
