import { CursorPaginatedResponse } from "@/types/sales";

export type ChartOfAccountType =
  | "asset"
  | "liability"
  | "equity"
  | "revenue"
  | "expense"
  | "cogs";

/** Morph map keys allowed by ListJournalEntriesRequest */
export const JOURNAL_REFERENCE_TYPES = [
  "user",
  "invoice",
  "credit_note",
  "bill",
  "grn",
  "supplier",
  "purchase_order",
  "supplier_return",
  "bill_payment",
  "stock_transfer",
  "adjustment",
  "product",
  "product_variant",
  "stock_level",
  "stock_movement",
  "journal_entry",
  "accounting_period",
  "report_job",
] as const;

export type JournalReferenceType = (typeof JOURNAL_REFERENCE_TYPES)[number];

export interface ChartOfAccountNode {
  id: number;
  parent_id: number | null;
  code: string;
  name: string;
  type: ChartOfAccountType | string;
  is_system?: boolean;
  children?: ChartOfAccountNode[];
}

export interface JournalEntryLine {
  id: number;
  coa_id: number;
  debit: number | string;
  credit: number | string;
  description?: string | null;
  account?: {
    id: number;
    code: string;
    name: string;
    type: string;
  } | null;
  created_at?: string;
}

export interface JournalEntry {
  id: number;
  branch_id: number;
  journal_entry_number?: string;
  description?: string | null;
  reference_type?: string | null;
  reference_id?: number | null;
  posted_at: string;
  branch?: { id: number; name: string } | null;
  lines?: JournalEntryLine[];
  created_at?: string;
}

export interface ManualJournalLinePayload {
  coa_id: number;
  debit: number | string;
  credit: number | string;
  description?: string | null;
}

export interface ManualJournalPayload {
  branch_id: number;
  posted_at: string;
  description: string;
  lines: ManualJournalLinePayload[];
}

export interface ListJournalEntriesParams {
  date_from?: string;
  date_to?: string;
  reference_type?: JournalReferenceType | string;
  per_page?: number;
  cursor?: string;
}

export interface AccountingPeriod {
  id: number;
  year: number;
  month: number;
  is_locked: boolean;
  locked_at?: string | null;
  locked_by_user_id?: number | null;
  created_at?: string;
  updated_at?: string;
}

export type JournalListResponse = CursorPaginatedResponse<JournalEntry>;

/** Flatten nested CoA tree for selects */
export function flattenChartOfAccounts(
  nodes: ChartOfAccountNode[],
  depth = 0,
): Array<ChartOfAccountNode & { depth: number; label: string }> {
  const rows: Array<ChartOfAccountNode & { depth: number; label: string }> = [];
  for (const node of nodes) {
    const indent = depth > 0 ? `${"— ".repeat(depth)}` : "";
    rows.push({
      ...node,
      depth,
      label: `${indent}${node.code} — ${node.name}`,
    });
    if (node.children?.length) {
      rows.push(...flattenChartOfAccounts(node.children, depth + 1));
    }
  }
  return rows;
}
