"use client";

import Link from "next/link";
import { useState } from "react";
import { AnalysisRunner } from "@/components/analysis/analysis-runner";
import { RequireAuth } from "@/components/require-auth";
import { ResumeManager } from "@/components/resume/resume-manager";
import { useAuth } from "@/lib/auth-context";

function DashboardContent() {
  const { user, logout, deleteAccount } = useAuth();
  const [deleting, setDeleting] = useState(false);

  async function handleDelete() {
    if (!window.confirm("Permanently delete your account and all data? This cannot be undone.")) {
      return;
    }
    setDeleting(true);
    try {
      await deleteAccount();
    } catch {
      setDeleting(false);
    }
  }

  return (
    <main className="mx-auto w-full max-w-3xl p-8">
      <header className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">AI CV Optimizer</h1>
          <p className="text-sm text-gray-600">Signed in as {user?.email}</p>
        </div>
        <div className="flex items-center gap-2">
          {user?.role === "admin" ? (
            <Link
              href="/admin"
              className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Admin
            </Link>
          ) : null}
          <button
            onClick={() => logout()}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Sign out
          </button>
        </div>
      </header>

      <section className="mb-10">
        <h2 className="mb-4 text-lg font-medium text-gray-900">Your resume</h2>
        <ResumeManager />
      </section>

      <section className="mb-10">
        <h2 className="mb-4 text-lg font-medium text-gray-900">Match analysis</h2>
        <AnalysisRunner />
      </section>

      <section className="border-t border-gray-200 pt-6">
        <h2 className="mb-2 text-sm font-medium text-gray-900">Danger zone</h2>
        <p className="mb-3 text-sm text-gray-500">
          Delete your account and all associated data (resume, analyses, cover letters).
        </p>
        <button
          onClick={() => void handleDelete()}
          disabled={deleting}
          className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
        >
          {deleting ? "Deleting…" : "Delete account"}
        </button>
      </section>
    </main>
  );
}

export default function DashboardPage() {
  return (
    <RequireAuth>
      <DashboardContent />
    </RequireAuth>
  );
}
