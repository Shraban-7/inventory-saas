"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { StatusBadge } from "@/components/shared/status-badge";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import {
  Package,
  ShieldCheck,
  Building2,
  Sliders,
  FileText,
  ShoppingCart,
  BookOpen,
  PieChart,
  Webhook,
  ArrowRight,
  Sparkles,
  Zap,
  Lock,
  Globe,
  Check,
  ChevronDown,
  ChevronUp,
  LayoutDashboard,
  LogIn,
  RefreshCw,
  TrendingUp,
  Boxes,
  Activity,
} from "lucide-react";
import { toast } from "sonner";

export default function SaaSNextLandingPage() {
  const router = useRouter();
  const { loginAsStubProfile } = useAuthStore();
  const { setActiveBranchId } = useShellStore();

  const [billingCycle, setBillingCycle] = React.useState<"monthly" | "annual">("annual");
  const [openFaq, setOpenFaq] = React.useState<number | null>(0);
  const [demoRole, setDemoRole] = React.useState<"Admin" | "Manager" | "Cashier" | "Accountant">("Admin");

  const handleLaunchTenant = (role: "Admin" | "Manager" | "Cashier" | "Accountant", planName?: string) => {
    loginAsStubProfile(role);
    setActiveBranchId(1);
    toast.success(`Access Granted as ${role}! Redirecting to Tenant Workspace...`);
    if (planName) {
      toast.info(`Selected Subscription Plan: ${planName}`);
    }
    router.push("/dashboard");
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-teal-500 selection:text-white">
      
      {/* Background Radial Glow */}
      <div className="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div className="absolute -top-40 -left-40 h-[600px] w-[600px] rounded-full bg-teal-600/15 blur-[140px]" />
        <div className="absolute top-1/3 -right-40 h-[600px] w-[600px] rounded-full bg-emerald-600/10 blur-[140px]" />
      </div>

      {/* Navigation Header */}
      <header className="sticky top-0 z-50 backdrop-blur-md bg-slate-950/80 border-b border-slate-800/80">
        <div className="mx-auto max-w-7xl px-6 h-16 flex items-center justify-between">
          
          {/* Logo */}
          <Link href="/" className="flex items-center gap-3 group">
            <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 p-0.5 shadow-lg shadow-teal-500/20 group-hover:scale-105 transition-transform">
              <div className="h-full w-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                <Package className="h-5 w-5 text-teal-400" />
              </div>
            </div>
            <div>
              <span className="font-bold text-lg tracking-tight bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
                OmniInventory
              </span>
              <span className="ml-2 text-[10px] font-mono px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-400 border border-teal-500/20">
                SaaS ERP
              </span>
            </div>
          </Link>

          {/* Navigation Links */}
          <nav className="hidden md:flex items-center gap-8 text-xs font-medium text-slate-300">
            <a href="#features" className="hover:text-teal-400 transition-colors">Features</a>
            <a href="#showcase" className="hover:text-teal-400 transition-colors">Live Showcase</a>
            <a href="#architecture" className="hover:text-teal-400 transition-colors">Architecture</a>
            <a href="#pricing" className="hover:text-teal-400 transition-colors">Pricing Plans</a>
            <a href="#faq" className="hover:text-teal-400 transition-colors">FAQ</a>
          </nav>

          {/* Action CTAs */}
          <div className="flex items-center gap-3">
            <Link href="/login">
              <Button variant="ghost" size="sm" className="text-xs text-slate-300 hover:text-white hover:bg-slate-900">
                <LogIn className="h-3.5 w-3.5 mr-1.5" /> Tenant Sign In
              </Button>
            </Link>

            <Button
              size="sm"
              onClick={() => handleLaunchTenant("Admin")}
              className="text-xs font-semibold bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white shadow-lg shadow-teal-500/25 border-none"
            >
              <LayoutDashboard className="h-3.5 w-3.5 mr-1.5" /> Launch Workspace
            </Button>
          </div>

        </div>
      </header>

      {/* Hero Section */}
      <section className="relative z-10 pt-20 pb-16 md:pt-28 md:pb-24 px-6 max-w-7xl mx-auto text-center">
        
        {/* Release Announcement Pill */}
        <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-medium mb-8 backdrop-blur-sm animate-pulse">
          <Sparkles className="h-3.5 w-3.5 text-teal-400" />
          <span>Production-Grade Multi-Tenant ERP Architecture Released</span>
          <ArrowRight className="h-3 w-3" />
        </div>

        {/* Headline */}
        <h1 className="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight max-w-5xl mx-auto leading-tight text-white">
          Unified Multi-Branch Inventory, Sales, Purchasing &amp;{" "}
          <span className="bg-gradient-to-r from-teal-400 via-emerald-400 to-cyan-400 bg-clip-text text-transparent">
            Double-Entry Accounting SaaS
          </span>
        </h1>

        {/* Subtitle */}
        <p className="mt-6 text-base sm:text-lg text-slate-400 max-w-3xl mx-auto leading-relaxed">
          Engineered for modern retail networks and distributors. Real-time multi-branch warehouse tracking, FIFO stock costing, automated accounts receivable/payable, and immutable general ledger reporting.
        </p>

        {/* Hero CTAs */}
        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
          <Button
            size="lg"
            onClick={() => handleLaunchTenant("Admin")}
            className="w-full sm:w-auto h-12 px-8 text-sm font-semibold bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white shadow-xl shadow-teal-500/30 rounded-xl"
          >
            <Zap className="h-4 w-4 mr-2" /> Start Free 14-Day Trial
          </Button>

          <a href="#showcase">
            <Button
              size="lg"
              variant="outline"
              className="w-full sm:w-auto h-12 px-8 text-sm font-semibold border-slate-800 bg-slate-900/60 hover:bg-slate-800 text-slate-200 rounded-xl"
            >
              <Activity className="h-4 w-4 mr-2 text-teal-400" /> Explore Interactive Demo
            </Button>
          </a>
        </div>

        {/* Metrics Bar */}
        <div className="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto border-t border-slate-800/80 pt-10">
          <div className="space-y-1">
            <div className="text-2xl sm:text-3xl font-extrabold text-white font-mono">100%</div>
            <div className="text-xs text-slate-400">Strict Tenant DB Isolation</div>
          </div>
          <div className="space-y-1">
            <div className="text-2xl sm:text-3xl font-extrabold text-teal-400 font-mono">FIFO</div>
            <div className="text-xs text-slate-400">Real-Time Cost Valuation</div>
          </div>
          <div className="space-y-1">
            <div className="text-2xl sm:text-3xl font-extrabold text-emerald-400 font-mono">RFC 7807</div>
            <div className="text-xs text-slate-400">Problem Details API Standard</div>
          </div>
          <div className="space-y-1">
            <div className="text-2xl sm:text-3xl font-extrabold text-white font-mono">&lt; 50ms</div>
            <div className="text-xs text-slate-400">Sub-Second Query Latency</div>
          </div>
        </div>

      </section>

      {/* Interactive Showcase Section */}
      <section id="showcase" className="relative z-10 py-16 px-6 max-w-7xl mx-auto">
        <div className="text-center mb-10 space-y-3">
          <Badge variant="outline" className="text-teal-400 border-teal-500/30 bg-teal-500/10">
            Live Workspace Preview
          </Badge>
          <h2 className="text-3xl font-bold text-white tracking-tight">
            See OmniInventory in Action
          </h2>
          <p className="text-sm text-slate-400 max-w-2xl mx-auto">
            Experience the real-time application shell with role-based access control and multi-branch scoping.
          </p>
        </div>

        {/* Interactive App Shell Preview Mockup */}
        <div className="rounded-2xl border border-slate-800 bg-slate-900/90 shadow-2xl overflow-hidden backdrop-blur-xl">
          
          {/* Top Mock Window Bar */}
          <div className="h-12 bg-slate-950 px-4 flex items-center justify-between border-b border-slate-800">
            <div className="flex items-center gap-2">
              <div className="h-3 w-3 rounded-full bg-red-500/80" />
              <div className="h-3 w-3 rounded-full bg-amber-500/80" />
              <div className="h-3 w-3 rounded-full bg-emerald-500/80" />
              <span className="ml-4 font-mono text-xs text-slate-400 hidden sm:inline">
                https://app.omniinventory.saas/dashboard
              </span>
            </div>

            {/* Quick Demo Role Switcher Bar */}
            <div className="flex items-center gap-2">
              <span className="text-[11px] text-slate-400 font-medium mr-1">Demo Role:</span>
              {(["Admin", "Manager", "Cashier", "Accountant"] as const).map((r) => (
                <button
                  key={r}
                  onClick={() => setDemoRole(r)}
                  className={`px-2.5 py-1 rounded text-[10px] font-semibold transition-colors ${
                    demoRole === r
                      ? "bg-teal-600 text-white shadow"
                      : "bg-slate-800 text-slate-400 hover:text-slate-200"
                  }`}
                >
                  {r}
                </button>
              ))}
            </div>
          </div>

          {/* Interactive Workspace Mock Content */}
          <div className="p-6 md:p-8 space-y-6">
            
            {/* KPI Cards Header */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="bg-slate-950/60 border-slate-800 text-white">
                <CardHeader className="py-3 px-4 flex flex-row items-center justify-between">
                  <CardTitle className="text-xs font-semibold text-slate-400">Total Stock Value</CardTitle>
                  <TrendingUp className="h-4 w-4 text-emerald-400" />
                </CardHeader>
                <CardContent className="px-4 pb-3">
                  <div className="text-2xl font-bold font-mono text-white">$148,290.00</div>
                  <div className="text-[11px] text-emerald-400 mt-1">FIFO Costing Valuation</div>
                </CardContent>
              </Card>

              <Card className="bg-slate-950/60 border-slate-800 text-white">
                <CardHeader className="py-3 px-4 flex flex-row items-center justify-between">
                  <CardTitle className="text-xs font-semibold text-slate-400">Active SKUs</CardTitle>
                  <Boxes className="h-4 w-4 text-teal-400" />
                </CardHeader>
                <CardContent className="px-4 pb-3">
                  <div className="text-2xl font-bold font-mono text-white">1,420 Items</div>
                  <div className="text-[11px] text-teal-400 mt-1">Across 3 Warehouses</div>
                </CardContent>
              </Card>

              <Card className="bg-slate-950/60 border-slate-800 text-white">
                <CardHeader className="py-3 px-4 flex flex-row items-center justify-between">
                  <CardTitle className="text-xs font-semibold text-slate-400">Monthly Sales AR</CardTitle>
                  <FileText className="h-4 w-4 text-cyan-400" />
                </CardHeader>
                <CardContent className="px-4 pb-3">
                  <div className="text-2xl font-bold font-mono text-white">$64,810.00</div>
                  <div className="text-[11px] text-cyan-400 mt-1">84% Paid / 16% Pending</div>
                </CardContent>
              </Card>

              <Card className="bg-slate-950/60 border-slate-800 text-white">
                <CardHeader className="py-3 px-4 flex flex-row items-center justify-between">
                  <CardTitle className="text-xs font-semibold text-slate-400">Active Tenant Scope</CardTitle>
                  <ShieldCheck className="h-4 w-4 text-amber-400" />
                </CardHeader>
                <CardContent className="px-4 pb-3">
                  <div className="text-2xl font-bold font-mono text-white">Role: {demoRole}</div>
                  <div className="text-[11px] text-amber-400 mt-1">Permissions Active</div>
                </CardContent>
              </Card>
            </div>

            {/* Dense Live Table Mockup */}
            <div className="rounded-xl border border-slate-800 bg-slate-950/80 overflow-hidden">
              <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                <div className="font-semibold text-xs text-slate-200 flex items-center gap-2">
                  <Activity className="h-4 w-4 text-teal-400" />
                  Live Operational Transactions (Branch #1 - Main Warehouse)
                </div>
                <Badge variant="outline" className="text-[10px] text-slate-400 border-slate-800">
                  Append-Only Audit Trail
                </Badge>
              </div>

              <Table dense>
                <TableHeader className="bg-slate-900/50">
                  <TableRow>
                    <TableHead className="text-slate-400">Entity Ref</TableHead>
                    <TableHead className="text-slate-400">Module</TableHead>
                    <TableHead className="text-slate-400">Party / Context</TableHead>
                    <TableHead className="text-slate-400">Amount / Delta</TableHead>
                    <TableHead className="text-slate-400">Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow>
                    <TableCell className="font-mono text-xs font-bold text-slate-200">INV-2026-041</TableCell>
                    <TableCell className="text-xs text-teal-400 font-medium">Sales Invoice</TableCell>
                    <TableCell className="text-xs text-slate-300">Acme Logistics Inc.</TableCell>
                    <TableCell className="font-mono text-xs font-bold text-teal-400">$3,450.00</TableCell>
                    <TableCell><StatusBadge status="paid" /></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell className="font-mono text-xs font-bold text-slate-200">PO-2026-089</TableCell>
                    <TableCell className="text-xs text-cyan-400 font-medium">Purchase Order</TableCell>
                    <TableCell className="text-xs text-slate-300">Global Supply Corp</TableCell>
                    <TableCell className="font-mono text-xs font-bold text-cyan-400">$12,800.00</TableCell>
                    <TableCell><StatusBadge status="confirmed" /></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell className="font-mono text-xs font-bold text-slate-200">GRN-2026-012</TableCell>
                    <TableCell className="text-xs text-emerald-400 font-medium">Goods Receipt</TableCell>
                    <TableCell className="text-xs text-slate-300">+250 Units Stock Intake</TableCell>
                    <TableCell className="font-mono text-xs font-bold text-emerald-400">+250.0000</TableCell>
                    <TableCell><StatusBadge status="completed" customLabel="Received" /></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell className="font-mono text-xs font-bold text-slate-200">JRN-2026-003</TableCell>
                    <TableCell className="text-xs text-purple-400 font-medium">Manual Journal</TableCell>
                    <TableCell className="text-xs text-slate-300">Balanced GL Posting</TableCell>
                    <TableCell className="font-mono text-xs font-bold text-purple-400">$5,000.00</TableCell>
                    <TableCell><StatusBadge status="approved" /></TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            {/* Launch Workspace Callout */}
            <div className="flex flex-col sm:flex-row items-center justify-between p-4 rounded-xl bg-gradient-to-r from-teal-950/40 via-slate-900 to-emerald-950/40 border border-teal-500/20 gap-4">
              <div className="text-xs text-slate-300">
                🚀 Ready to evaluate the full system as <strong className="text-white">{demoRole}</strong>?
              </div>
              <Button
                size="sm"
                onClick={() => handleLaunchTenant(demoRole)}
                className="w-full sm:w-auto text-xs bg-teal-600 hover:bg-teal-500 text-white font-semibold"
              >
                Launch Workspace as {demoRole} <ArrowRight className="h-3.5 w-3.5 ml-1" />
              </Button>
            </div>

          </div>
        </div>
      </section>

      {/* Core Features & Modules Grid */}
      <section id="features" className="relative z-10 py-20 px-6 max-w-7xl mx-auto">
        <div className="text-center mb-14 space-y-3">
          <Badge variant="outline" className="text-teal-400 border-teal-500/30 bg-teal-500/10">
            End-to-End SaaS Architecture
          </Badge>
          <h2 className="text-3xl sm:text-4xl font-bold text-white tracking-tight">
            Complete Enterprise Capability Matrix
          </h2>
          <p className="text-sm text-slate-400 max-w-2xl mx-auto">
            From purchasing and inventory FIFO batching to automated invoicing, credit notes, and general ledger journal postings.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          
          {/* Feature 1 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <Building2 className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">Multi-Tenant &amp; Multi-Branch</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Complete database isolation (`tenant_id`) paired with per-branch inventory scoping across physical warehouses.
              </CardDescription>
            </CardHeader>
          </Card>

          {/* Feature 2 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <Sliders className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">FIFO Inventory Valuation</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Automated cost batching, manual adjustments (In/Out), low-stock thresholds, and inter-branch transfers.
              </CardDescription>
            </CardHeader>
          </Card>

          {/* Feature 3 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <FileText className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">Sales &amp; Accounts Receivable</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Customer directory, cursor-paginated sales invoices, multi-method payment receipt ledgers, and credit notes.
              </CardDescription>
            </CardHeader>
          </Card>

          {/* Feature 4 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <ShoppingCart className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">Procurement &amp; Goods Receipts</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Vendor suppliers, purchase orders (draft, confirm, cancel), GRN intake, and vendor bill payments.
              </CardDescription>
            </CardHeader>
          </Card>

          {/* Feature 5 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <BookOpen className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">Double-Entry General Ledger</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Standard Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expenses), manual journals, and period locking.
              </CardDescription>
            </CardHeader>
          </Card>

          {/* Feature 6 */}
          <Card className="bg-slate-900/60 border-slate-800 hover:border-teal-500/40 transition-all hover:shadow-xl hover:shadow-teal-500/5 group">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <PieChart className="h-5 w-5 text-teal-400" />
              </div>
              <CardTitle className="text-base text-white">Asynchronous Financial Reports</CardTitle>
              <CardDescription className="text-xs text-slate-400">
                Background calculation queue for Profit &amp; Loss statements with live polling and financial summary exports.
              </CardDescription>
            </CardHeader>
          </Card>

        </div>
      </section>

      {/* Subscription Pricing Plans Section */}
      <section id="pricing" className="relative z-10 py-20 px-6 max-w-7xl mx-auto">
        <div className="text-center mb-12 space-y-3">
          <Badge variant="outline" className="text-teal-400 border-teal-500/30 bg-teal-500/10">
            Flexible Subscription Plans
          </Badge>
          <h2 className="text-3xl sm:text-4xl font-bold text-white tracking-tight">
            Transparent Pricing for Growing Businesses
          </h2>
          <p className="text-sm text-slate-400 max-w-2xl mx-auto">
            Choose the ideal subscription tier for your company. Change or upgrade at any time.
          </p>

          {/* Billing Cycle Toggle */}
          <div className="inline-flex items-center gap-3 p-1 rounded-xl bg-slate-900 border border-slate-800 mt-6">
            <button
              onClick={() => setBillingCycle("monthly")}
              className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                billingCycle === "monthly"
                  ? "bg-teal-600 text-white shadow"
                  : "text-slate-400 hover:text-slate-200"
              }`}
            >
              Monthly Billing
            </button>
            <button
              onClick={() => setBillingCycle("annual")}
              className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
                billingCycle === "annual"
                  ? "bg-teal-600 text-white shadow"
                  : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <span>Annual Billing</span>
              <span className="text-[10px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded font-bold">
                Save 20%
              </span>
            </button>
          </div>
        </div>

        {/* Pricing Cards Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
          
          {/* Starter Plan */}
          <Card className="bg-slate-900/60 border-slate-800 text-slate-100 flex flex-col justify-between">
            <CardHeader className="space-y-3">
              <div className="font-semibold text-sm text-slate-400">Starter Tier</div>
              <div className="flex items-baseline gap-1">
                <span className="text-4xl font-extrabold text-white font-mono">
                  ${billingCycle === "annual" ? "39" : "49"}
                </span>
                <span className="text-xs text-slate-400">/ month</span>
              </div>
              <CardDescription className="text-xs text-slate-400">
                Essential inventory and invoicing for single-location shops.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <ul className="space-y-2.5 text-xs text-slate-300">
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Up to 2 Authorized Branches
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> 5 User Team Seats
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Standard Product Catalog &amp; SKUs
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Sales Invoicing &amp; Receipts
                </li>
              </ul>

              <Button
                onClick={() => handleLaunchTenant("Cashier", "Starter Plan")}
                variant="outline"
                className="w-full text-xs font-semibold border-slate-700 bg-slate-800 hover:bg-slate-700 text-white"
              >
                Choose Starter
              </Button>
            </CardContent>
          </Card>

          {/* Business Pro Plan (Popular) */}
          <Card className="bg-slate-900 border-2 border-teal-500 text-slate-100 flex flex-col justify-between relative shadow-2xl shadow-teal-500/10">
            <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 text-white text-[10px] font-bold uppercase tracking-wider shadow">
              Most Popular
            </div>
            <CardHeader className="space-y-3">
              <div className="font-semibold text-sm text-teal-400">Business Pro</div>
              <div className="flex items-baseline gap-1">
                <span className="text-4xl font-extrabold text-white font-mono">
                  ${billingCycle === "annual" ? "119" : "149"}
                </span>
                <span className="text-xs text-slate-400">/ month</span>
              </div>
              <CardDescription className="text-xs text-slate-400">
                Complete ERP suite for multi-branch retailers &amp; wholesalers.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <ul className="space-y-2.5 text-xs text-slate-300">
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Unlimited Authorized Branches
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> 25 User Team Seats (All RBAC Roles)
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> FIFO Stock Costing &amp; Transfers
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Purchasing POs, GRNs &amp; Bills
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Double-Entry GL &amp; Period Locking
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Bulk CSV Imports &amp; Error Logs
                </li>
              </ul>

              <Button
                onClick={() => handleLaunchTenant("Manager", "Business Pro Plan")}
                className="w-full text-xs font-semibold bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white shadow-lg shadow-teal-500/25"
              >
                Choose Business Pro
              </Button>
            </CardContent>
          </Card>

          {/* Enterprise Plan */}
          <Card className="bg-slate-900/60 border-slate-800 text-slate-100 flex flex-col justify-between">
            <CardHeader className="space-y-3">
              <div className="font-semibold text-sm text-slate-400">Enterprise</div>
              <div className="flex items-baseline gap-1">
                <span className="text-4xl font-extrabold text-white font-mono">
                  ${billingCycle === "annual" ? "319" : "399"}
                </span>
                <span className="text-xs text-slate-400">/ month</span>
              </div>
              <CardDescription className="text-xs text-slate-400">
                Dedicated tenant hosting, custom webhooks, and 24/7 SLA.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <ul className="space-y-2.5 text-xs text-slate-300">
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Dedicated Isolated Database Host
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Unlimited Team Seats
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Webhook Event Signing &amp; Dispatcher
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> 99.99% Uptime Guarantee SLA
                </li>
                <li className="flex items-center gap-2">
                  <Check className="h-4 w-4 text-teal-400 shrink-0" /> Dedicated Account Representative
                </li>
              </ul>

              <Button
                onClick={() => handleLaunchTenant("Admin", "Enterprise Plan")}
                variant="outline"
                className="w-full text-xs font-semibold border-slate-700 bg-slate-800 hover:bg-slate-700 text-white"
              >
                Contact Enterprise Sales
              </Button>
            </CardContent>
          </Card>

        </div>
      </section>

      {/* Architecture & Security Highlights */}
      <section id="architecture" className="relative z-10 py-16 px-6 max-w-7xl mx-auto border-t border-slate-800/80">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          
          <div className="space-y-6">
            <Badge variant="outline" className="text-teal-400 border-teal-500/30 bg-teal-500/10">
              Technical Standards &amp; Compliance
            </Badge>
            <h2 className="text-3xl font-bold text-white tracking-tight">
              Built on Modern Standards &amp; Zero-Trust Rules
            </h2>
            <p className="text-sm text-slate-400 leading-relaxed">
              OmniInventory enforces strict architectural standards across API endpoints, data validation, and idempotency guarantees.
            </p>

            <div className="space-y-4 text-xs">
              <div className="flex items-start gap-3 p-3 rounded-lg bg-slate-900 border border-slate-800">
                <Lock className="h-5 w-5 text-teal-400 shrink-0 mt-0.5" />
                <div>
                  <strong className="text-white block">RFC 7807 Problem Details Error Standard</strong>
                  <span className="text-slate-400">All API validation and HTTP exceptions return unified structured JSON objects.</span>
                </div>
              </div>

              <div className="flex items-start gap-3 p-3 rounded-lg bg-slate-900 border border-slate-800">
                <RefreshCw className="h-5 w-5 text-emerald-400 shrink-0 mt-0.5" />
                <div>
                  <strong className="text-white block">Idempotency-Key Header Protection</strong>
                  <span className="text-slate-400">State-modifying calls (invoices, adjustments, transfers, POs, journals) enforce idempotency UUIDs.</span>
                </div>
              </div>

              <div className="flex items-start gap-3 p-3 rounded-lg bg-slate-900 border border-slate-800">
                <Webhook className="h-5 w-5 text-cyan-400 shrink-0 mt-0.5" />
                <div>
                  <strong className="text-white block">HMAC SHA-256 Signed Webhooks</strong>
                  <span className="text-slate-400">Dispatches signed event payloads with automatic retry mechanisms and secret validation.</span>
                </div>
              </div>
            </div>
          </div>

          {/* Code Snippet Card */}
          <div className="rounded-2xl border border-slate-800 bg-slate-950 p-6 font-mono text-xs shadow-2xl text-slate-300 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3 text-slate-400">
              <span className="flex items-center gap-2"><Globe className="h-4 w-4 text-teal-400" /> POST /api/v1/invoices</span>
              <span className="text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded">201 Created</span>
            </div>

            <pre className="text-teal-300 text-[11px] overflow-x-auto leading-relaxed">
{`// HTTP Headers Enforced:
Authorization: Bearer <sanctum_token>
X-Tenant-ID: 104
Idempotency-Key: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
Content-Type: application/json

// Request Body Payload:
{
  "branch_id": 1,
  "customer_id": 42,
  "invoice_date": "2026-07-23",
  "items": [
    { "variant_id": 12, "quantity": 2, "unit_price": 45.00 }
  ]
}`}
            </pre>
          </div>

        </div>
      </section>

      {/* FAQ Accordion Section */}
      <section id="faq" className="relative z-10 py-16 px-6 max-w-4xl mx-auto border-t border-slate-800/80">
        <div className="text-center mb-12 space-y-3">
          <Badge variant="outline" className="text-teal-400 border-teal-500/30 bg-teal-500/10">
            Frequently Asked Questions
          </Badge>
          <h2 className="text-3xl font-bold text-white tracking-tight">
            Everything You Need to Know
          </h2>
        </div>

        <div className="space-y-4">
          {[
            {
              q: "How does multi-tenant database isolation work?",
              a: "Each tenant request is automatically scoped by Laravel global query scopes and tenant middleware (`current_tenant_id()`). Data is isolated so no tenant can ever read or write another organization's records."
            },
            {
              q: "Can I manage inventory across multiple physical branches?",
              a: "Yes! OmniInventory provides per-branch stock level scoping, inter-branch transfer tracking, and branch-restricted user permissions."
            },
            {
              q: "How does the FIFO inventory costing method work?",
              a: "When goods are received (via GRNs), cost batches are recorded. When sales invoices or stock adjustments occur, costs are deducted strictly on a First-In, First-Out basis to calculate accurate Gross Profit margins."
            },
            {
              q: "What security measures protect state-modifying requests?",
              a: "All state-changing operations enforce Sanctum Bearer Token authorization, tenant ownership verification, and Idempotency-Key UUID headers to prevent accidental duplicate entries."
            },
          ].map((faq, idx) => (
            <div
              key={idx}
              className="rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden transition-colors"
            >
              <button
                onClick={() => setOpenFaq(openFaq === idx ? null : idx)}
                className="w-full p-4 text-left flex items-center justify-between font-semibold text-sm text-slate-200 hover:text-white"
              >
                <span>{faq.q}</span>
                {openFaq === idx ? <ChevronUp className="h-4 w-4 text-teal-400" /> : <ChevronDown className="h-4 w-4 text-slate-500" />}
              </button>
              {openFaq === idx && (
                <div className="px-4 pb-4 text-xs text-slate-400 leading-relaxed border-t border-slate-800/60 pt-3">
                  {faq.a}
                </div>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* Final CTA Banner */}
      <section className="relative z-10 py-16 px-6 max-w-7xl mx-auto">
        <div className="rounded-3xl bg-gradient-to-r from-teal-900/50 via-slate-900 to-emerald-900/50 border border-teal-500/30 p-10 md:p-16 text-center space-y-6 shadow-2xl relative overflow-hidden">
          <div className="absolute -right-20 -bottom-20 h-64 w-64 rounded-full bg-teal-500/10 blur-3xl pointer-events-none" />
          
          <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight max-w-2xl mx-auto">
            Transform Your Inventory &amp; Financial Operations Today
          </h2>
          <p className="text-sm text-slate-300 max-w-xl mx-auto">
            Get started with a 14-day free trial. Experience instant access to all multi-branch, invoicing, and accounting features.
          </p>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <Button
              size="lg"
              onClick={() => handleLaunchTenant("Admin")}
              className="w-full sm:w-auto h-12 px-8 text-sm font-semibold bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white shadow-xl shadow-teal-500/30 rounded-xl"
            >
              Launch Tenant Workspace Now
            </Button>
            <Link href="/login">
              <Button
                size="lg"
                variant="outline"
                className="w-full sm:w-auto h-12 px-8 text-sm font-semibold border-slate-700 bg-slate-900/80 hover:bg-slate-800 text-slate-200 rounded-xl"
              >
                Sign In to Existing Tenant
              </Button>
            </Link>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="relative z-10 border-t border-slate-900 bg-slate-950/90 py-12 px-6 text-slate-400 text-xs">
        <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
          
          <div className="space-y-3">
            <div className="flex items-center gap-2">
              <Package className="h-5 w-5 text-teal-400" />
              <span className="font-bold text-sm text-white">OmniInventory</span>
            </div>
            <p className="text-slate-500 text-[11px] leading-relaxed">
              Production-Grade Multi-Tenant ERP System. Built with Laravel Clean Architecture &amp; Next.js 15 App Router.
            </p>
          </div>

          <div>
            <div className="font-semibold text-white mb-3 text-xs">Core Modules</div>
            <ul className="space-y-2 text-[11px]">
              <li><Link href="/inventory/products" className="hover:text-teal-400 transition-colors">Products &amp; Variants</Link></li>
              <li><Link href="/inventory/stock" className="hover:text-teal-400 transition-colors">Multi-Branch Stock</Link></li>
              <li><Link href="/sales/invoices" className="hover:text-teal-400 transition-colors">Sales &amp; Invoicing</Link></li>
              <li><Link href="/purchasing/orders" className="hover:text-teal-400 transition-colors">Purchase Orders &amp; GRN</Link></li>
            </ul>
          </div>

          <div>
            <div className="font-semibold text-white mb-3 text-xs">Finance &amp; Admin</div>
            <ul className="space-y-2 text-[11px]">
              <li><Link href="/accounting/coa" className="hover:text-teal-400 transition-colors">Chart of Accounts</Link></li>
              <li><Link href="/accounting/journals" className="hover:text-teal-400 transition-colors">Double-Entry Journals</Link></li>
              <li><Link href="/reports/profit-and-loss" className="hover:text-teal-400 transition-colors">P&amp;L Financial Reports</Link></li>
              <li><Link href="/webhooks" className="hover:text-teal-400 transition-colors">Webhook Dispatcher</Link></li>
            </ul>
          </div>

          <div>
            <div className="font-semibold text-white mb-3 text-xs">System Health</div>
            <div className="flex items-center gap-2 text-emerald-400 text-[11px] font-mono mb-2">
              <span className="h-2 w-2 rounded-full bg-emerald-400 animate-ping" />
              All Systems Operational (RFC 7807)
            </div>
            <p className="text-slate-500 text-[10px]">
              Version 1.0.0 · MIT Licensed · Multi-Branch Tenant Ready
            </p>
          </div>

        </div>

        <div className="max-w-7xl mx-auto border-t border-slate-900 pt-6 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-500">
          <div>&copy; {new Date().getFullYear()} OmniInventory SaaS ERP. All rights reserved.</div>
          <div className="flex gap-4 mt-2 sm:mt-0">
            <a href="#features" className="hover:text-slate-300">Privacy Policy</a>
            <a href="#features" className="hover:text-slate-300">Terms of Service</a>
            <a href="#architecture" className="hover:text-slate-300">API Documentation</a>
          </div>
        </div>
      </footer>

    </div>
  );
}
