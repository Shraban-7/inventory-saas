"use client";

import * as React from "react";
import { ChevronDown, ChevronRight, FolderTree } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { ChartOfAccountNode, ChartOfAccountType } from "@/types/accounting";
import { cn } from "@/lib/utils";

const TYPE_STYLES: Record<string, string> = {
  asset: "bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300",
  liability: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300",
  equity: "bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300",
  revenue: "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300",
  expense: "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300",
  cogs: "bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300",
};

export function AccountTypeBadge({ type }: { type: ChartOfAccountType | string }) {
  const key = (type || "").toLowerCase();
  return (
    <Badge
      variant="outline"
      className={cn("text-[10px] uppercase tracking-wide border-0", TYPE_STYLES[key] || "")}
    >
      {type}
    </Badge>
  );
}

function TreeNode({
  node,
  depth,
}: {
  node: ChartOfAccountNode;
  depth: number;
}) {
  const hasChildren = (node.children?.length || 0) > 0;
  const [open, setOpen] = React.useState(depth < 2);

  return (
    <div>
      <div
        className={cn(
          "flex items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-slate-50 dark:hover:bg-slate-900/60",
        )}
        style={{ paddingLeft: `${depth * 16 + 8}px` }}
      >
        {hasChildren ? (
          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            className="text-slate-400 hover:text-slate-700"
            aria-label={open ? "Collapse" : "Expand"}
          >
            {open ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
          </button>
        ) : (
          <span className="w-3.5" />
        )}
        <FolderTree className="h-3.5 w-3.5 text-teal-600 shrink-0" />
        <span className="font-mono font-semibold text-slate-700 dark:text-slate-200">{node.code}</span>
        <span className="font-medium text-slate-900 dark:text-slate-100 truncate">{node.name}</span>
        <AccountTypeBadge type={node.type} />
        {node.is_system ? (
          <Badge variant="secondary" className="text-[10px]">
            System
          </Badge>
        ) : null}
        <span className="ml-auto font-mono text-[10px] text-slate-400">#{node.id}</span>
      </div>
      {hasChildren && open && (
        <div>
          {node.children!.map((child) => (
            <TreeNode key={child.id} node={child} depth={depth + 1} />
          ))}
        </div>
      )}
    </div>
  );
}

export function ChartOfAccountsTree({ roots }: { roots: ChartOfAccountNode[] }) {
  if (roots.length === 0) {
    return null;
  }

  return (
    <div className="divide-y divide-slate-100 dark:divide-slate-800">
      {roots.map((node) => (
        <TreeNode key={node.id} node={node} depth={0} />
      ))}
    </div>
  );
}
