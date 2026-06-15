import type { Analysis, ScoreBreakdown } from "@/types/api";

const CATEGORY_LABELS: Record<keyof ScoreBreakdown, string> = {
  hard_skills: "Hard skills",
  soft_skills: "Soft skills",
  experience: "Experience",
  education: "Education",
  keywords: "Keywords",
};

export function AnalysisResult({ analysis }: { analysis: Analysis }) {
  if (analysis.status === "queued" || analysis.status === "processing") {
    return (
      <div className="rounded-xl border border-gray-200 p-6 text-sm text-gray-500">
        Analysing your resume against the job description…
      </div>
    );
  }

  if (analysis.status === "failed") {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
        {analysis.error_message ?? "The analysis failed. Please try again."}
      </div>
    );
  }

  const breakdown = analysis.score_breakdown;

  return (
    <div className="flex flex-col gap-6 rounded-xl border border-gray-200 p-6">
      <div className="flex items-baseline gap-3">
        <span className="text-4xl font-semibold text-gray-900">{analysis.overall_score}</span>
        <span className="text-sm text-gray-500">/ 100 match score</span>
      </div>

      {analysis.explanation ? (
        <p className="text-sm text-gray-700">{analysis.explanation}</p>
      ) : null}

      {breakdown ? (
        <div className="flex flex-col gap-2">
          {(Object.keys(CATEGORY_LABELS) as (keyof ScoreBreakdown)[]).map((key) => (
            <div key={key} className="flex items-center gap-3">
              <span className="w-28 shrink-0 text-xs text-gray-600">{CATEGORY_LABELS[key]}</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                <div className="h-full rounded-full bg-gray-900" style={{ width: `${breakdown[key]}%` }} />
              </div>
              <span className="w-8 shrink-0 text-right text-xs tabular-nums text-gray-600">
                {breakdown[key]}
              </span>
            </div>
          ))}
        </div>
      ) : null}

      <div className="grid gap-6 sm:grid-cols-2">
        <div>
          <h3 className="mb-2 text-sm font-medium text-gray-900">
            Matched skills ({analysis.matched?.length ?? 0})
          </h3>
          <ul className="flex flex-col gap-1">
            {(analysis.matched ?? []).map((m, i) => (
              <li key={i} className="text-sm text-gray-700">
                <span className="font-medium">{m.requirement}</span>
                {m.matched_skill ? (
                  <span className="text-gray-500"> ↔ {m.matched_skill}</span>
                ) : null}
                {m.similarity !== null ? (
                  <span className="text-gray-400"> ({Math.round(m.similarity * 100)}%)</span>
                ) : null}
              </li>
            ))}
            {(analysis.matched?.length ?? 0) === 0 ? (
              <li className="text-sm text-gray-400">No matches found.</li>
            ) : null}
          </ul>
        </div>

        <div>
          <h3 className="mb-2 text-sm font-medium text-gray-900">
            Gaps ({analysis.gaps?.length ?? 0})
          </h3>
          <ul className="flex flex-col gap-1">
            {(analysis.gaps ?? []).map((g, i) => (
              <li key={i} className="text-sm text-gray-700">
                <span className="font-medium">{g.requirement}</span>
                {g.category ? (
                  <span className="text-gray-400"> · {g.category.replace("_", " ")}</span>
                ) : null}
              </li>
            ))}
            {(analysis.gaps?.length ?? 0) === 0 ? (
              <li className="text-sm text-gray-400">No gaps — great match!</li>
            ) : null}
          </ul>
        </div>
      </div>
    </div>
  );
}
