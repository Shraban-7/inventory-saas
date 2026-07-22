"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import {
  Search,
  X,
  Package,
  FileText,
  ShoppingCart,
  Users,
  Truck,
  BookOpen,
  PieChart,
  Settings,
  Webhook,
} from "lucide-react";
import { useShellStore } from "@/lib/stores/shell-store";
import { useAuthStore } from "@/lib/stores/auth-store";
import { RoleName } from "@/types/auth";

interface RouteJump {
  title: string;
  category: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
  role?: RoleName;
}

const COMMAND_ROUTES: RouteJump[] = [
  { title: "Dashboard Overview", category: "General", href: "/dashboard", icon: Package },
  { title: "Customers Directory", category: "Sales", href: "/sales/customers", icon: Users, permission: "invoice.view" },
  { title: "Sales Invoices", category: "Sales", href: "/sales/invoices", icon: FileText, permission: "invoice.view" },
  { title: "Credit Notes", category: "Sales", href: "/sales/credit-notes", icon: FileText, permission: "invoice.view" },
  { title: "Products & SKUs Catalog", category: "Inventory", href: "/inventory/products", icon: Package, permission: "product.manage" },
  { title: "Multi-Branch Stock Levels", category: "Inventory", href: "/inventory/stock", icon: Package, permission: "product.manage" },
  { title: "Manual Stock Adjustments", category: "Inventory", href: "/inventory/adjustments", icon: Package, permission: "stock.adjust" },
  { title: "Inter-Branch Stock Transfers", category: "Inventory", href: "/inventory/transfers", icon: Package, permission: "stock.transfer" },
  { title: "Bulk CSV Imports", category: "Inventory", href: "/inventory/imports", icon: Package, permission: "product.manage" },
  { title: "Suppliers Directory", category: "Purchasing", href: "/purchasing/suppliers", icon: Truck, permission: "purchase.create" },
  { title: "Purchase Orders", category: "Purchasing", href: "/purchasing/orders", icon: ShoppingCart, permission: "purchase.create" },
  { title: "Goods Receipt Notes (GRN)", category: "Purchasing", href: "/purchasing/grn", icon: Truck, permission: "purchase.receive" },
  { title: "Vendor Bills & Payments", category: "Purchasing", href: "/purchasing/bills", icon: ShoppingCart, permission: "purchase.create" },
  { title: "Chart of Accounts Tree", category: "Accounting", href: "/accounting/coa", icon: BookOpen, permission: "report.view" },
  { title: "General Ledger Journals", category: "Accounting", href: "/accounting/journals", icon: BookOpen, permission: "report.view" },
  { title: "Post Manual Journal", category: "Accounting", href: "/accounting/journals/new", icon: BookOpen, role: "Accountant" },
  { title: "Accounting Period Locking", category: "Accounting", href: "/accounting/periods", icon: BookOpen, role: "Admin" },
  { title: "Profit & Loss Job Generator", category: "Reports", href: "/reports/profit-and-loss", icon: PieChart, permission: "report.view" },
  { title: "Webhooks Subscriptions", category: "Admin", href: "/webhooks", icon: Webhook, role: "Admin" },
  { title: "Settings (deferred gaps)", category: "Admin", href: "/settings", icon: Settings, role: "Admin" },
];

export function CommandPalette() {
  const router = useRouter();
  const { commandPaletteOpen, setCommandPaletteOpen } = useShellStore();
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const hasRole = useAuthStore((s) => s.hasRole);
  const [search, setSearch] = React.useState("");
  const [activeIndex, setActiveIndex] = React.useState(0);
  const inputRef = React.useRef<HTMLInputElement>(null);

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

  React.useEffect(() => {
    if (commandPaletteOpen) {
      setTimeout(() => {
        setActiveIndex(0);
        inputRef.current?.focus();
      }, 0);
    } else {
      setTimeout(() => setSearch(""), 0);
    }
  }, [commandPaletteOpen]);

  const filtered = COMMAND_ROUTES.filter((r) => {
    if (r.role && !hasRole(r.role)) return false;
    if (r.permission && !hasPermission(r.permission)) return false;
    const q = search.toLowerCase();
    return (
      r.title.toLowerCase().includes(q) || r.category.toLowerCase().includes(q)
    );
  });

  React.useEffect(() => {
    setTimeout(() => setActiveIndex(0), 0);
  }, [search]);

  if (!commandPaletteOpen) return null;

  const handleSelect = (href: string) => {
    setCommandPaletteOpen(false);
    setSearch("");
    router.push(href);
  };

  const onInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setActiveIndex((i) => Math.min(i + 1, Math.max(filtered.length - 1, 0)));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActiveIndex((i) => Math.max(i - 1, 0));
    } else if (e.key === "Enter" && filtered[activeIndex]) {
      e.preventDefault();
      handleSelect(filtered[activeIndex].href);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Command palette"
    >
      <div
        className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        onClick={() => setCommandPaletteOpen(false)}
        aria-hidden="true"
      />

      <div className="relative z-50 w-full max-w-xl rounded-lg border border-slate-200 bg-white shadow-2xl overflow-hidden dark:border-slate-800 dark:bg-slate-950 animate-in zoom-in-95">
        <div className="flex items-center border-b border-slate-200 px-3 dark:border-slate-800">
          <Search className="h-4 w-4 text-slate-400 mr-2 shrink-0" aria-hidden="true" />
          <input
            ref={inputRef}
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={onInputKeyDown}
            placeholder="Type a page name to jump directly..."
            className="h-12 w-full bg-transparent text-sm placeholder:text-slate-400 focus:outline-none dark:text-slate-100"
            aria-label="Search pages"
            aria-controls="command-palette-results"
            aria-activedescendant={
              filtered[activeIndex] ? `command-item-${activeIndex}` : undefined
            }
          />
          <button
            onClick={() => setCommandPaletteOpen(false)}
            className="p-1 rounded-sm text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
            aria-label="Close command palette"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div id="command-palette-results" className="max-h-80 overflow-y-auto p-2" role="listbox">
          {filtered.length === 0 ? (
            <div className="p-6 text-center text-xs text-slate-500" role="status">
              No matching pages found.
            </div>
          ) : (
            <div className="space-y-1">
              {filtered.map((item, index) => {
                const Icon = item.icon;
                const isActive = index === activeIndex;
                return (
                  <button
                    key={item.href}
                    id={`command-item-${index}`}
                    role="option"
                    aria-selected={isActive}
                    onClick={() => handleSelect(item.href)}
                    onMouseEnter={() => setActiveIndex(index)}
                    className={
                      isActive
                        ? "flex w-full items-center justify-between rounded-md px-3 py-2.5 text-xs bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200 cursor-pointer"
                        : "flex w-full items-center justify-between rounded-md px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900 cursor-pointer"
                    }
                  >
                    <div className="flex items-center gap-2.5">
                      <Icon className="h-4 w-4 text-teal-600 dark:text-teal-400" aria-hidden="true" />
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
          <span>
            <kbd className="font-semibold">↑↓</kbd> move · <kbd className="font-semibold">Enter</kbd> open ·{" "}
            <kbd className="font-semibold">Esc</kbd> close
          </span>
          <span>Navigation jump</span>
        </div>
      </div>
    </div>
  );
}
