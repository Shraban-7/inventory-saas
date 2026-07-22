"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ProfitAndLossResult } from "@/types/reports";
import { formatCurrency } from "@/lib/utils";

const ROWS: Array<{ key: keyof ProfitAndLossResult; label: string; emphasize?: boolean }> = [
  { key: "revenue", label: "Revenue" },
  { key: "cogs", label: "Cost of Goods Sold (COGS)" },
  { key: "gross_profit", label: "Gross Profit", emphasize: true },
  { key: "operating_expenses", label: "Operating Expenses" },
  { key: "net_profit", label: "Net Profit", emphasize: true },
];

export function ProfitAndLossStatement({ result }: { result: ProfitAndLossResult }) {
  return (
    <Card>
      <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
        <CardTitle className="text-sm font-semibold">Profit &amp; Loss Statement</CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        <table className="w-full text-sm">
          <tbody>
            {ROWS.map((row) => {
              const value = Number(result[row.key] || 0);
              return (
                <tr
                  key={row.key}
                  className="border-b border-slate-100 dark:border-slate-800 last:border-0"
                >
                  <td
                    className={`px-4 py-3 ${row.emphasize ? "font-semibold text-slate-900 dark:text-slate-100" : "text-slate-600 dark:text-slate-400"}`}
                  >
                    {row.label}
                  </td>
                  <td
                    className={`px-4 py-3 text-right font-mono tabular-nums ${
                      row.emphasize
                        ? value < 0
                          ? "font-bold text-rose-600"
                          : "font-bold text-teal-700 dark:text-teal-400"
                        : "text-slate-800 dark:text-slate-200"
                    }`}
                  >
                    {formatCurrency(value)}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </CardContent>
    </Card>
  );
}
