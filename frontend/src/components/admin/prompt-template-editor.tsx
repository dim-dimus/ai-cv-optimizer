"use client";

import { useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { getPromptTemplates, updatePromptTemplate } from "@/lib/admin-api";
import { useAuth } from "@/lib/auth-context";
import type { PromptTemplate } from "@/types/api";

export function PromptTemplateEditor() {
  const { token } = useAuth();
  const [templates, setTemplates] = useState<PromptTemplate[]>([]);
  const [selected, setSelected] = useState<PromptTemplate | null>(null);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    void (async () => {
      try {
        const list = await getPromptTemplates(token);
        if (cancelled) return;
        setTemplates(list);
        setSelected(list[0] ?? null);
      } catch {
        if (!cancelled) setError("Could not load templates.");
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token]);

  function patch(field: keyof PromptTemplate, value: string | number | boolean) {
    setSelected((prev) => (prev ? { ...prev, [field]: value } : prev));
  }

  async function save() {
    if (!token || !selected) return;
    setError(null);
    setNotice(null);
    setSaving(true);
    try {
      const updated = await updatePromptTemplate(token, selected.slug, {
        content: selected.content,
        model: selected.model,
        max_tokens: Number(selected.max_tokens),
        temperature: Number(selected.temperature),
        is_active: selected.is_active,
      });
      setSelected(updated);
      setTemplates((prev) => prev.map((t) => (t.slug === updated.slug ? updated : t)));
      setNotice(`Saved — now version ${updated.version}. Changes take effect immediately.`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not save the template.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-2">
        {templates.map((t) => (
          <button
            key={t.slug}
            onClick={() => setSelected(t)}
            className={`rounded-md border px-3 py-1.5 text-sm ${
              selected?.slug === t.slug
                ? "border-gray-900 bg-gray-900 text-white"
                : "border-gray-300 text-gray-700 hover:bg-gray-50"
            }`}
          >
            {t.slug}
          </button>
        ))}
      </div>

      {error ? <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      {notice ? <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{notice}</p> : null}

      {selected ? (
        <div className="flex flex-col gap-3">
          <div className="flex flex-wrap gap-3">
            <label className="flex flex-col gap-1 text-xs text-gray-600">
              Model
              <input
                value={selected.model}
                onChange={(e) => patch("model", e.target.value)}
                className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              />
            </label>
            <label className="flex flex-col gap-1 text-xs text-gray-600">
              Max tokens
              <input
                type="number"
                value={selected.max_tokens}
                onChange={(e) => patch("max_tokens", Number(e.target.value))}
                className="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              />
            </label>
            <label className="flex flex-col gap-1 text-xs text-gray-600">
              Temperature
              <input
                type="number"
                step="0.1"
                value={selected.temperature}
                onChange={(e) => patch("temperature", Number(e.target.value))}
                className="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              />
            </label>
            <label className="flex items-center gap-2 self-end text-sm text-gray-700">
              <input
                type="checkbox"
                checked={selected.is_active}
                onChange={(e) => patch("is_active", e.target.checked)}
              />
              Active
            </label>
          </div>

          <textarea
            value={selected.content}
            onChange={(e) => patch("content", e.target.value)}
            rows={16}
            className="w-full rounded-md border border-gray-300 p-3 font-mono text-xs outline-none focus:border-gray-900"
          />

          <div className="flex items-center gap-3">
            <button
              onClick={() => void save()}
              disabled={saving}
              className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
            >
              {saving ? "Saving…" : "Save template"}
            </button>
            <span className="text-xs text-gray-500">Version {selected.version}</span>
          </div>
        </div>
      ) : null}
    </div>
  );
}
