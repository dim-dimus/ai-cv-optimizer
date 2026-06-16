"use client";

import Link from "next/link";
import { useState } from "react";
import { LogsTable, UsageDashboard, UsersTable } from "@/components/admin/admin-panels";
import { PromptTemplateEditor } from "@/components/admin/prompt-template-editor";
import { RequireAdmin } from "@/components/require-admin";

const TABS = ["Templates", "Usage", "Users", "Logs"] as const;
type Tab = (typeof TABS)[number];

function AdminContent() {
  const [tab, setTab] = useState<Tab>("Templates");

  return (
    <main className="mx-auto w-full max-w-4xl p-8">
      <header className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Admin</h1>
        <Link href="/dashboard" className="text-sm text-gray-600 underline">
          Back to app
        </Link>
      </header>

      <nav className="mb-6 flex gap-2 border-b border-gray-200">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
              tab === t
                ? "border-gray-900 text-gray-900"
                : "border-transparent text-gray-500 hover:text-gray-700"
            }`}
          >
            {t}
          </button>
        ))}
      </nav>

      {tab === "Templates" ? <PromptTemplateEditor /> : null}
      {tab === "Usage" ? <UsageDashboard /> : null}
      {tab === "Users" ? <UsersTable /> : null}
      {tab === "Logs" ? <LogsTable /> : null}
    </main>
  );
}

export default function AdminPage() {
  return (
    <RequireAdmin>
      <AdminContent />
    </RequireAdmin>
  );
}
