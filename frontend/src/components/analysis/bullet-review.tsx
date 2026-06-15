"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError } from "@/lib/api";
import { generateBullets, getBullets, updateBullet } from "@/lib/bullets-api";
import { useAuth } from "@/lib/auth-context";
import type { BulletStatus, BulletSuggestion } from "@/types/api";

const MAX_POLLS = 20;

const STATUS_STYLES: Record<BulletStatus, string> = {
  pending: "bg-gray-100 text-gray-600",
  accepted: "bg-green-100 text-green-700",
  rejected: "bg-red-100 text-red-700",
  edited: "bg-blue-100 text-blue-700",
};

export function BulletReview({ analysisId }: { analysisId: number }) {
  const { token } = useAuth();

  const [bullets, setBullets] = useState<BulletSuggestion[]>([]);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editText, setEditText] = useState("");
  const pollsRef = useRef(0);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    void (async () => {
      try {
        const list = await getBullets(token, analysisId);
        if (!cancelled) setBullets(list);
      } catch {
        // Non-fatal.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token, analysisId]);

  // Poll for results after requesting generation (there is no job-status endpoint).
  useEffect(() => {
    if (!token || !generating) return;
    let cancelled = false;
    const interval = setInterval(() => {
      void (async () => {
        pollsRef.current += 1;
        try {
          const list = await getBullets(token, analysisId);
          if (cancelled) return;
          if (list.length > 0 || pollsRef.current >= MAX_POLLS) {
            setBullets(list);
            setGenerating(false);
          }
        } catch {
          if (!cancelled && pollsRef.current >= MAX_POLLS) setGenerating(false);
        }
      })();
    }, 2000);
    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [token, analysisId, generating]);

  const generate = useCallback(async () => {
    if (!token) return;
    setError(null);
    pollsRef.current = 0;
    setGenerating(true);
    try {
      await generateBullets(token, analysisId);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not generate suggestions.");
      setGenerating(false);
    }
  }, [token, analysisId]);

  const setStatus = useCallback(
    async (id: number, status: BulletStatus, edited?: string) => {
      if (!token) return;
      try {
        const updated = await updateBullet(token, id, status, edited);
        setBullets((prev) => prev.map((b) => (b.id === id ? updated : b)));
        setEditingId(null);
      } catch (err) {
        setError(err instanceof ApiError ? err.message : "Could not save your choice.");
      }
    },
    [token],
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-3">
        <button
          onClick={() => void generate()}
          disabled={generating}
          className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
        >
          {generating ? "Generating…" : bullets.length > 0 ? "Regenerate" : "Suggest bullet improvements"}
        </button>
        {generating ? <span className="text-xs text-gray-500">This can take a few seconds…</span> : null}
      </div>

      {error ? <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}

      {!generating && bullets.length === 0 ? (
        <p className="text-sm text-gray-500">No suggestions yet.</p>
      ) : null}

      <ul className="flex flex-col gap-3">
        {bullets.map((bullet) => (
          <li key={bullet.id} className="rounded-lg border border-gray-200 p-4">
            <div className="mb-2 flex items-center justify-between">
              <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[bullet.status]}`}>
                {bullet.status}
              </span>
            </div>
            <p className="text-sm text-gray-400 line-through">{bullet.original_text}</p>
            <p className="mt-1 text-sm font-medium text-gray-900">
              {bullet.status === "edited" && bullet.edited_text ? bullet.edited_text : bullet.suggested_text}
            </p>
            {bullet.rationale ? (
              <p className="mt-1 text-xs text-gray-500">{bullet.rationale}</p>
            ) : null}

            {editingId === bullet.id ? (
              <div className="mt-3 flex flex-col gap-2">
                <textarea
                  value={editText}
                  onChange={(e) => setEditText(e.target.value)}
                  rows={3}
                  className="w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:border-gray-900"
                />
                <div className="flex gap-2">
                  <button
                    onClick={() => void setStatus(bullet.id, "edited", editText)}
                    className="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                  >
                    Save edit
                  </button>
                  <button
                    onClick={() => setEditingId(null)}
                    className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            ) : (
              <div className="mt-3 flex gap-2">
                <button
                  onClick={() => void setStatus(bullet.id, "accepted")}
                  className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                >
                  Accept
                </button>
                <button
                  onClick={() => void setStatus(bullet.id, "rejected")}
                  className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                >
                  Reject
                </button>
                <button
                  onClick={() => {
                    setEditingId(bullet.id);
                    setEditText(bullet.edited_text ?? bullet.suggested_text);
                  }}
                  className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                >
                  Edit
                </button>
              </div>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
