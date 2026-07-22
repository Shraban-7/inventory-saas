"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { Search, X, Package, FileText, ShoppingCart, Users, Truck, BookOpen, PieChart, Settings } from "lucide-react";
import { useShellStore } from "@/lib/stores/shell-store";

interface RouteJump {
  title: string;
  category: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
}

const COMMAND_ROUTES: RouteJump[] = [
  { title: "Dashboard Overview", category: "General", href: "/dashboard", icon: Package },
  { title: "Customers Directory", category: "Sales", href: "/sales/customers", icon: Users },
  { title: "Sales Invoices", category: "Sales", href: "/sales/invoices", icon: FileText },
  { title: "Credit Notes", category: "Sales", href: "/sales/credit-notes", icon: FileText },
  { title: "Products & SKUs Catalog", category: "Inventory", href: "/inventory/products", icon: Package },
  { title: "Multi-Branch Stock Levels", category: "Inventory", href: "/inventory/stock", icon: Package },
  { title: "Manual Stock Adjustments", category: "Inventory", href: "/inventory/adjustments", icon: Package },
  { title: "Inter-Branch Stock Transfers", category: "Inventory", href: "/inventory/transfers", icon: Package },
  { title: "Bulk CSV Imports", category: "Inventory", href: "/inventory/imports", icon: Package },
  { title: "Suppliers Directory", category: "Purchasing", href: "/purchasing/suppliers", icon: Truck },
  { title: "Purchase Orders", category: "Purchasing", href: "/purchasing/orders", icon: ShoppingCart },
  { title: "Goods Receipt Notes (GRN)", category: "Purchasing", href: "/purchasing/grn", icon: Truck },
  { title: "Vendor Bills & Payments", category: "Purchasing", href: "/purchasing/bills", icon: ShoppingCart },
  { title: "Chart of Accounts Tree", category: "Accounting", href: "/accounting/coa", icon: BookOpen },
  { title: "General Ledger Journals", category: "Accounting", href: "/accounting/journals", icon: BookOpen },
  { title: "Post Manual Journal", category: "Accounting", href: "/accounting/journals/new", icon: BookOpen },
  { title: "Accounting Period Locking", category: "Accounting", href: "/accounting/periods", icon: BookOpen },
  { title: "Profit & Loss Job Generator", category: "Reports", href: "/reports/profit-and-loss", icon: PieChart },
  { title: "Webhooks Subscriptions", category: "Admin", href: "/webhooks", icon: Settings },
  { title: "Settings & System Logs", category: "Admin", href: "/settings", icon: Settings },
];

export function CommandPalette() {
  const router = useRouter();
  const { commandPaletteOpen, setCommandPaletteOpen } = useShellStore();
  const [search, setSearch] = React.useState("");

  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setCommandPaletteOpen(!commandPaletteOpen);
      }
      if (e.key === "Escape" && commandPaletteOpen) {
        setCommandPaletteOpen(false);
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [commandPaletteOpen, setCommandPaletteOpen]);

  if (!commandPaletteOpen) return null;

  const filtered = COMMAND_ROUTES.filter((r) =>
    r.title.toLowerCase().includes(search.toLowerCase()) ||
    r.category.toLowerCase().includes(search.toLowerCase())
  );

  const handleSelect = (href: string) => {
    setCommandPaletteOpen(false);
    setSearch("");
    router.push(href);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        onClick={() => setCommandPaletteOpen(false)}
      />

      {/* Modal */}
      <div className="relative z-50 w-full max-w-xl rounded-lg border border-slate-200 bg-white shadow-2xl overflow-hidden dark:border-slate-800 dark:bg-slate-950 animate-in zoom-in-95">
        <div className="flex items-center border-b border-slate-200 px-3 dark:border-slate-800">
          <Search className="h-4 w-4 text-slate-400 mr-2 shrink-0" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Type a page name to jump directly..."
            className="h-12 w-full bg-transparent text-sm placeholder:text-slate-400 focus:outline-none dark:text-slate-100"
            autoFocus
          />
          <button
            onClick={() => setCommandPaletteOpen(false)}
            className="p-1 rounded-sm text-slate-400 hover:text-slate-600"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="max-h-80 overflow-y-auto p-2">
          {filtered.length === 0 ? (
            <div className="p-6 text-center text-xs text-slate-500">
              No matching pages found.
            </div>
          ) : (
            <div className="space-y-1">
              {filtered.map((item, index) => {
                const Icon = item.icon;
                return (
                  <button
                    key={index}
                    onClick={() => handleSelect(item.href)}
                    className="flex w-full items-center justify-between rounded-md px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900 cursor-pointer"
                  >
                    <div className="flex items-center gap-2.5">
                      <Icon className="h-4 w-4 text-teal-600 dark:text-teal-400" />
                      <span className="font-medium text-slate-900 dark:text-slate-100">{item.title}</span>
                    </div>
                    <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                      {item.category}
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="border-t border-slate-100 bg-slate-50 px-3 py-2 text-[10px] text-slate-500 dark:border-slate-800 dark:bg-slate-900 flex justify-between">
          <span>Use <strong>ESC</strong> to exit</span>
          <span>Navigation Jump Mode</span>
        </div>
      </div>
    </div>
  );
}
