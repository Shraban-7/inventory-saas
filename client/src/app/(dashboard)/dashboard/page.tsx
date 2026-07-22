"use client";

import * as React from "react";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { Package, ShoppingCart, Users, Layers, ArrowUpRight } from "lucide-react";
import Link from "next/link";

export default function DashboardPage() {
  const { user, roles } = useAuthStore();
  const { activeBranchId } = useShellStore();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational Dashboard"
        description={`Executive snapshot for Authorized Branch #${activeBranchId || 1}.`}
        actions={
          <div className="flex gap-2">
            <Link href="/sales/invoices">
              <Button size="sm">+ New Invoice</Button>
            </Link>
            <Link href="/purchasing/orders">
              <Button size="sm" variant="outline">+ New PO</Button>
            </Link>
          </div>
        }
      />

      {/* Role Banner */}
      <Card className="bg-teal-500/10 border-teal-500/20">
        <CardContent className="p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-600 text-white font-bold">
              {roles[0]?.charAt(0) || "A"}
            </div>
            <div>
              <div className="font-semibold text-slate-900 dark:text-slate-100">
                Welcome back, {user?.name || "User"}!
              </div>
              <div className="text-xs text-slate-600 dark:text-slate-400">
                Logged in as <span className="font-semibold text-teal-700 dark:text-teal-400">{roles[0] || "User"}</span> (Tenant #{user?.tenant_id || 1}).
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* KPI Cards Placeholder Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Active Products</CardDescription>
            <CardTitle className="text-2xl font-bold flex justify-between items-center">
              <span>--</span>
              <Package className="h-5 w-5 text-teal-600" />
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-slate-500">
            <Link href="/inventory/products" className="text-teal-600 font-medium inline-flex items-center gap-1 hover:underline">
              View catalog <ArrowUpRight className="h-3 w-3" />
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Sales Invoices</CardDescription>
            <CardTitle className="text-2xl font-bold flex justify-between items-center">
              <span>--</span>
              <ShoppingCart className="h-5 w-5 text-teal-600" />
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-slate-500">
            <Link href="/sales/invoices" className="text-teal-600 font-medium inline-flex items-center gap-1 hover:underline">
              View invoices <ArrowUpRight className="h-3 w-3" />
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Purchase Orders</CardDescription>
            <CardTitle className="text-2xl font-bold flex justify-between items-center">
              <span>--</span>
              <Layers className="h-5 w-5 text-teal-600" />
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-slate-500">
            <Link href="/purchasing/orders" className="text-teal-600 font-medium inline-flex items-center gap-1 hover:underline">
              View POs <ArrowUpRight className="h-3 w-3" />
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs">Registered Customers</CardDescription>
            <CardTitle className="text-2xl font-bold flex justify-between items-center">
              <span>--</span>
              <Users className="h-5 w-5 text-teal-600" />
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-slate-500">
            <Link href="/sales/customers" className="text-teal-600 font-medium inline-flex items-center gap-1 hover:underline">
              View directory <ArrowUpRight className="h-3 w-3" />
            </Link>
          </CardContent>
        </Card>
      </div>

    </div>
  );
}
