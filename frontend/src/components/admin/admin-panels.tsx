"use client";

import { useEffect, useState } from "react";
import { getLlmLogs, getUsage, getUsers } from "@/lib/admin-api";
import { useAuth } from "@/lib/auth-context";
import type { AdminUser, LlmLogEntry, UsageSummary } from "@/types/api";

export function UsageDashboard() {
  const { token } = useAuth();
  const [usage, setUsage] = useState<UsageSummary | null>(null);

  useEffect(() => {
    if (!token) return;
    void getUsage(token).then(setUsage).catch(() => undefined);
  }, [token]);

  if (!usage) return <p className="text-sm text-gray-500">Loading usage…</p>;

  const cards = [
    { label: "Calls", value: usage.totals.calls.toLocaleString() },
    { label: "Tokens", value: usage.totals.tokens.toLocaleString() },
    { label: "Cost", value: `$${usage.totals.cost_usd.toFixed(4)}` },
    { label: "Failures", value: usage.totals.failures.toLocaleString() },
  ];

  return (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {cards.map((c) => (
          <div key={c.label} className="rounded-lg border border-gray-200 p-4">
            <p className="text-xs text-gray-500">{c.label}</p>
            <p className="text-xl font-semibold text-gray-900">{c.value}</p>
          </div>
        ))}
      </div>

      <table className="w-full text-left text-sm">
        <thead className="text-xs text-gray-500">
          <tr>
            <th className="py-2">Operation</th>
            <th>Provider</th>
            <th className="text-right">Calls</th>
            <th className="text-right">Tokens</th>
            <th className="text-right">Cost</th>
          </tr>
        </thead>
        <tbody>
          {usage.by_operation.map((r, i) => (
            <tr key={i} className="border-t border-gray-100">
              <td className="py-2">{r.operation}</td>
              <td>{r.provider}</td>
              <td className="text-right tabular-nums">{r.calls.toLocaleString()}</td>
              <td className="text-right tabular-nums">{r.tokens.toLocaleString()}</td>
              <td className="text-right tabular-nums">${r.cost_usd.toFixed(4)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function UsersTable() {
  const { token } = useAuth();
  const [users, setUsers] = useState<AdminUser[]>([]);

  useEffect(() => {
    if (!token) return;
    void getUsers(token).then(setUsers).catch(() => undefined);
  }, [token]);

  return (
    <table className="w-full text-left text-sm">
      <thead className="text-xs text-gray-500">
        <tr>
          <th className="py-2">Email</th>
          <th>Role</th>
          <th className="text-right">Analyses</th>
          <th className="text-center">Resume</th>
        </tr>
      </thead>
      <tbody>
        {users.map((u) => (
          <tr key={u.id} className="border-t border-gray-100">
            <td className="py-2">{u.email}</td>
            <td>{u.role}</td>
            <td className="text-right tabular-nums">{u.analyses_count}</td>
            <td className="text-center">{u.has_resume ? "✓" : "—"}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

export function LogsTable() {
  const { token } = useAuth();
  const [logs, setLogs] = useState<LlmLogEntry[]>([]);

  useEffect(() => {
    if (!token) return;
    void getLlmLogs(token).then(setLogs).catch(() => undefined);
  }, [token]);

  return (
    <table className="w-full text-left text-sm">
      <thead className="text-xs text-gray-500">
        <tr>
          <th className="py-2">Operation</th>
          <th>Model</th>
          <th>Status</th>
          <th className="text-right">Tokens</th>
          <th className="text-right">Cost</th>
          <th className="text-right">Latency</th>
        </tr>
      </thead>
      <tbody>
        {logs.map((l) => (
          <tr key={l.id} className="border-t border-gray-100">
            <td className="py-2">{l.operation}</td>
            <td className="text-gray-500">{l.model}</td>
            <td className={l.status === "failed" ? "text-red-600" : "text-green-600"}>{l.status}</td>
            <td className="text-right tabular-nums">{l.total_tokens.toLocaleString()}</td>
            <td className="text-right tabular-nums">${l.cost_usd.toFixed(4)}</td>
            <td className="text-right tabular-nums">{l.latency_ms ?? "—"} ms</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
