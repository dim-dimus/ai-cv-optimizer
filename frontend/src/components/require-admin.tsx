"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useAuth } from "@/lib/auth-context";

/**
 * Client-side guard for /admin. Server-side authorization is still enforced by
 * the API (admin middleware) — this is only for UX.
 */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const { status, user } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    } else if (status === "authenticated" && user?.role !== "admin") {
      router.replace("/dashboard");
    }
  }, [status, user, router]);

  if (status !== "authenticated" || user?.role !== "admin") {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-gray-500">
        Loading…
      </div>
    );
  }

  return <>{children}</>;
}
