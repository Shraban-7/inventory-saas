"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronRight, Home } from "lucide-react";

export function Breadcrumbs() {
  const pathname = usePathname();
  const segments = pathname.split("/").filter(Boolean);

  if (segments.length === 0) return null;

  return (
    <nav aria-label="Breadcrumb" className="flex items-center space-x-1.5 text-xs text-slate-500 mb-4 dark:text-slate-400">
      <Link href="/dashboard" className="flex items-center hover:text-slate-900 dark:hover:text-slate-100">
        <Home className="h-3.5 w-3.5" />
      </Link>

      {segments.map((segment, index) => {
        const href = `/${segments.slice(0, index + 1).join("/")}`;
        const isLast = index === segments.length - 1;
        const formatted = segment
          .replace(/-/g, " ")
          .replace(/\b\w/g, (l) => l.toUpperCase());

        return (
          <React.Fragment key={href}>
            <ChevronRight className="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
            {isLast ? (
              <span className="font-semibold text-slate-900 dark:text-slate-100">{formatted}</span>
            ) : (
              <Link href={href} className="hover:text-slate-900 dark:hover:text-slate-100 capitalize">
                {formatted}
              </Link>
            )}
          </React.Fragment>
        );
      })}
    </nav>
  );
}
