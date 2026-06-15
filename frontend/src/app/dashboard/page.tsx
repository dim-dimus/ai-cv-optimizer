"use client";

import { RequireAuth } from "@/components/require-auth";
import { useAuth } from "@/lib/auth-context";

function DashboardContent() {
  const { user, logout } = useAuth();

  return (
    <main className="mx-auto w-full max-w-3xl p-8">
      <header className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">AI CV Optimizer</h1>
          <p className="text-sm text-gray-600">Signed in as {user?.email}</p>
        </div>
        <button
          onClick={() => logout()}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Sign out
        </button>
      </header>

      <section className="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
        Your resume and analyses will appear here. Resume upload arrives in the next phase.
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
