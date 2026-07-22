"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Truck,
  BookOpen,
  BarChart3,
  Webhook,
  Settings,
  Users,
  FileText,
  RotateCcw,
  Boxes,
  ArrowLeftRight,
  Sliders,
  Upload,
  UserCheck,
  ClipboardList,
  Receipt,
  PieChart,
  Lock,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useShellStore } from "@/lib/stores/shell-store";
import { PermissionGuard } from "@/components/shared/permission-guard";

interface NavItem {
  title: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
}

interface NavGroup {
  groupTitle: string;
  items: NavItem[];
}

const NAVIGATION_GROUPS: NavGroup[] = [
  {
    groupTitle: "Overview",
    items: [
      { title: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
    ],
  },
  {
    groupTitle: "Sales",
    items: [
      { title: "Customers", href: "/sales/customers", icon: Users, permission: "invoice.view" },
      { title: "Invoices", href: "/sales/invoices", icon: FileText, permission: "invoice.view" },
      { title: "Credit Notes", href: "/sales/credit-notes", icon: RotateCcw, permission: "invoice.view" },
    ],
  },
  {
    groupTitle: "Inventory",
    items: [
      { title: "Products & SKUs", href: "/inventory/products", icon: Package },
      { title: "Stock Levels", href: "/inventory/stock", icon: Boxes },
      { title: "Stock Adjustments", href: "/inventory/adjustments", icon: Sliders, permission: "stock.adjust" },
      { title: "Stock Transfers", href: "/inventory/transfers", icon: ArrowLeftRight, permission: "stock.transfer" },
      { title: "Bulk CSV Imports", href: "/inventory/imports", icon: Upload, permission: "product.manage" },
    ],
  },
  {
    groupTitle: "Purchasing",
    items: [
      { title: "Suppliers", href: "/purchasing/suppliers", icon: UserCheck, permission: "purchase.create" },
      { title: "Purchase Orders", href: "/purchasing/orders", icon: ShoppingCart, permission: "purchase.create" },
      { title: "Goods Receipt (GRN)", href: "/purchasing/grn", icon: Truck, permission: "purchase.receive" },
      { title: "Vendor Bills", href: "/purchasing/bills", icon: Receipt, permission: "purchase.create" },
    ],
  },
  {
    groupTitle: "Accounting & GL",
    items: [
      { title: "Chart of Accounts", href: "/accounting/coa", icon: BookOpen, permission: "report.view" },
      { title: "Journal Entries", href: "/accounting/journals", icon: ClipboardList, permission: "report.view" },
      { title: "Period Locks", href: "/accounting/periods", icon: Lock, permission: "report.view" },
    ],
  },
  {
    groupTitle: "Intelligence",
    items: [
      { title: "Profit & Loss", href: "/reports/profit-and-loss", icon: PieChart, permission: "report.view" },
      { title: "Report Job Monitor", href: "/reports/jobs", icon: BarChart3, permission: "report.view" },
    ],
  },
  {
    groupTitle: "System Admin",
    items: [
      { title: "Webhooks", href: "/webhooks", icon: Webhook },
      { title: "Settings & Audit", href: "/settings", icon: Settings },
    ],
  },
];

export function Sidebar() {
  const pathname = usePathname();
  const { sidebarCollapsed } = useShellStore();

  return (
    <aside
      className={cn(
        "flex flex-col border-r border-slate-200 bg-white transition-all duration-300 dark:border-slate-800 dark:bg-slate-950 shrink-0",
        sidebarCollapsed ? "w-16" : "w-64"
      )}
    >
      <div className="flex-1 overflow-y-auto py-4 px-3 space-y-6">
        {NAVIGATION_GROUPS.map((group, groupIdx) => (
          <div key={groupIdx} className="space-y-1">
            {!sidebarCollapsed && (
              <div className="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {group.groupTitle}
              </div>
            )}

            <div className="space-y-0.5">
              {group.items.map((item) => {
                const isActive = pathname === item.href || pathname.startsWith(item.href + "/");
                const Icon = item.icon;

                return (
                  <PermissionGuard key={item.href} permission={item.permission}>
                    <Link
                      href={item.href}
                      className={cn(
                        "flex items-center gap-3 rounded-md px-3 py-2 text-xs font-medium transition-colors cursor-pointer",
                        isActive
                          ? "bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300 font-semibold"
                          : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100"
                      )}
                      title={sidebarCollapsed ? item.title : undefined}
                    >
                      <Icon className={cn("h-4 w-4 shrink-0", isActive ? "text-teal-600 dark:text-teal-400" : "text-slate-400")} />
                      {!sidebarCollapsed && <span>{item.title}</span>}
                    </Link>
                  </PermissionGuard>
                );
              })}
            </div>
          </div>
        ))}
      </div>
    </aside>
  );
}
