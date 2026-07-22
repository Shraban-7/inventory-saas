"use client";

import * as React from "react";
import Link from "next/link";
import { Package, Search, ShieldCheck, LogOut, ChevronDown, Building2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { useShellStore } from "@/lib/stores/shell-store";
import { useAuthStore } from "@/lib/stores/auth-store";
import { Badge } from "@/components/ui/badge";

export function Topbar() {
  const { activeBranchId, setActiveBranchId, toggleCommandPalette, toggleSidebar } = useShellStore();
  const { user, branches, logout, roles } = useAuthStore();
  const [showProfileMenu, setShowProfileMenu] = React.useState(false);

  const branchOptions = branches.map((b) => ({
    label: b.name,
    value: b.id,
  }));

  return (
    <header className="sticky top-0 z-40 flex h-14 w-full items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur-xs dark:border-slate-800 dark:bg-slate-950/95">
      {/* Left: Sidebar Toggle + Brand */}
      <div className="flex items-center gap-3">
        <Button
          variant="ghost"
          size="icon"
          onClick={toggleSidebar}
          className="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
          aria-label="Toggle Sidebar"
        >
          <Package className="h-5 w-5 text-teal-600 dark:text-teal-400" />
        </Button>

        <Link href="/dashboard" className="flex items-center gap-2 font-bold text-slate-900 dark:text-slate-100">
          <span className="hidden sm:inline">Inventory SaaS</span>
          <Badge variant="outline" className="text-[10px] px-1.5 py-0">v1 API</Badge>
        </Link>

        {/* Branch Context Switcher */}
        <div className="ml-2 hidden items-center gap-1.5 md:flex">
          <Building2 className="h-4 w-4 text-slate-400" />
          <Select
            value={activeBranchId || undefined}
            onChange={(e) => setActiveBranchId(Number(e.target.value))}
            options={branchOptions}
            className="h-8 text-xs font-medium border-slate-200 dark:border-slate-800"
          />
        </div>
      </div>

      {/* Middle: Command Palette Trigger */}
      <div className="flex-1 max-w-md mx-4">
        <button
          onClick={toggleCommandPalette}
          className="flex h-9 w-full items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 text-xs text-slate-500 shadow-2xs transition-colors hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer"
        >
          <span className="flex items-center gap-2">
            <Search className="h-3.5 w-3.5" />
            Quick jump to page...
          </span>
          <kbd className="pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border border-slate-200 bg-white px-1.5 font-mono text-[10px] font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <span className="text-xs">⌘</span>K
          </kbd>
        </button>
      </div>

      {/* Right: Idempotency Status + Profile Menu */}
      <div className="flex items-center gap-3">
        <div className="hidden lg:flex items-center gap-1 text-[11px] font-medium text-teal-700 bg-teal-50 px-2.5 py-1 rounded-full dark:bg-teal-950 dark:text-teal-300">
          <ShieldCheck className="h-3.5 w-3.5" />
          Idempotency Active
        </div>

        {/* User Profile Dropdown */}
        <div className="relative">
          <button
            onClick={() => setShowProfileMenu(!showProfileMenu)}
            className="flex items-center gap-2 rounded-full border border-slate-200 p-1 pl-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900 cursor-pointer"
          >
            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200 font-bold">
              {user?.name ? user.name.charAt(0) : "U"}
            </div>
            <span className="hidden md:inline">{user?.name || "User"}</span>
            <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
          </button>

          {showProfileMenu && (
            <div className="absolute right-0 mt-2 w-56 rounded-md border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-800 dark:bg-slate-950 z-50 animate-in fade-in-50">
              <div className="border-b border-slate-100 pb-2 px-2 dark:border-slate-800">
                <div className="font-semibold text-slate-900 dark:text-slate-100">{user?.name}</div>
                <div className="text-xs text-slate-500 dark:text-slate-400">{user?.email}</div>
                <div className="mt-1">
                  <Badge variant="secondary" className="text-[10px]">
                    Role: {roles[0] || "User"}
                  </Badge>
                </div>
              </div>

              <div className="pt-2">
                <button
                  onClick={() => {
                    setShowProfileMenu(false);
                    logout();
                  }}
                  className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 cursor-pointer"
                >
                  <LogOut className="h-3.5 w-3.5" />
                  Sign Out
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
