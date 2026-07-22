"use client";

import * as React from "react";
import Link from "next/link";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Dialog } from "@/components/ui/dialog";
import { Sheet } from "@/components/ui/sheet";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { Package, Layers, Info, AlertTriangle, Plus, LayoutDashboard, LogIn } from "lucide-react";
import { formatCurrency, formatQuantity } from "@/lib/utils";

export default function DesignSystemShowcase() {
  const [isDialogOpen, setIsDialogOpen] = React.useState(false);
  const [isSheetOpen, setIsSheetOpen] = React.useState(false);
  const [selectedDate, setSelectedDate] = React.useState("2026-07-22");

  return (
    <main className="min-h-screen bg-slate-50 p-6 md:p-10 dark:bg-slate-950">
      <div className="mx-auto max-w-6xl space-y-10">
        
        {/* Header */}
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b border-slate-200 pb-6 dark:border-slate-800">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-3">
              <Package className="h-8 w-8 text-teal-600 dark:text-teal-400" />
              Inventory SaaS Portal & Design System
            </h1>
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Enterprise Multi-Tenant Portal (Teal / Slate Theme, App Shell Chrome & Auth Ready)
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Link href="/dashboard">
              <Button className="gap-2">
                <LayoutDashboard className="h-4 w-4" /> Open Application Shell
              </Button>
            </Link>
            <Link href="/login">
              <Button variant="outline" className="gap-2">
                <LogIn className="h-4 w-4" /> Sign In
              </Button>
            </Link>
          </div>
        </div>

        {/* Status Badges Section */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Layers className="h-5 w-5 text-teal-600" />
              Semantic Status Badges (Backend Enums)
            </CardTitle>
            <CardDescription>
              Standardized status mapping matching backend entities (Invoices, Bills, POs, Stock Adjustments, Report Jobs).
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-3">
            <StatusBadge status="draft" />
            <StatusBadge status="pending" />
            <StatusBadge status="confirmed" />
            <StatusBadge status="approved" />
            <StatusBadge status="completed" />
            <StatusBadge status="paid" />
            <StatusBadge status="partially_paid" />
            <StatusBadge status="unpaid" />
            <StatusBadge status="void" />
            <StatusBadge status="cancelled" />
            <StatusBadge status="stock_adjustment_in" />
            <StatusBadge status="stock_adjustment_out" />
            <StatusBadge status="low_stock" />
            <StatusBadge status="out_of_stock" />
            <StatusBadge status="queued" />
            <StatusBadge status="running" />
          </CardContent>
        </Card>

        {/* Buttons & Tabs Section */}
        <Tabs defaultValue="buttons">
          <TabsList>
            <TabsTrigger value="buttons">Buttons & Variants</TabsTrigger>
            <TabsTrigger value="forms">Form Controls</TabsTrigger>
            <TabsTrigger value="tables">Dense Table Spec</TabsTrigger>
          </TabsList>

          <TabsContent value="buttons" className="mt-4">
            <Card>
              <CardHeader>
                <CardTitle>Button Variants & Sizes</CardTitle>
                <CardDescription>Interactive button states with built-in loading spinners.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-3">
                  <Button variant="default">Default Primary</Button>
                  <Button variant="secondary">Secondary</Button>
                  <Button variant="outline">Outline</Button>
                  <Button variant="destructive">Destructive</Button>
                  <Button variant="ghost">Ghost</Button>
                  <Button variant="link">Link Button</Button>
                  <Button isLoading>Submitting</Button>
                </div>
                <Separator className="my-2" />
                <div className="flex items-center gap-3">
                  <Button size="sm">Small (sm)</Button>
                  <Button size="default">Default (md)</Button>
                  <Button size="lg">Large (lg)</Button>
                  <Button size="icon"><Plus className="h-4 w-4" /></Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="forms" className="mt-4">
            <Card>
              <CardHeader>
                <CardTitle>Form Controls & Data Inputs</CardTitle>
                <CardDescription>Inputs, selects, textareas, and Y-m-d date pickers with validation states.</CardDescription>
              </CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label required>Product Name</Label>
                  <Input placeholder="e.g., Ergonomic Mechanical Keyboard" />
                </div>

                <div className="space-y-2">
                  <Label required>Branch Selection</Label>
                  <Select options={[
                    { label: "Main Warehouse (Branch #1)", value: 1 },
                    { label: "Downtown Retail Store (Branch #2)", value: 2 },
                  ]} />
                </div>

                <div className="space-y-2">
                  <DatePicker
                    label="Invoice / Order Date"
                    value={selectedDate}
                    onChange={(d) => setSelectedDate(d)}
                  />
                </div>

                <div className="space-y-2">
                  <Label>Description / Notes</Label>
                  <Textarea placeholder="Enter transaction notes..." rows={2} />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="tables" className="mt-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle>Data Table (Dense Mode Spec)</CardTitle>
                  <CardDescription>High-density tabular alignment with tabular-nums for numeric columns.</CardDescription>
                </div>
                <Button size="sm" variant="outline" onClick={() => setIsDialogOpen(true)}>
                  Test Modal Dialog
                </Button>
              </CardHeader>
              <CardContent>
                <Table dense>
                  <TableHeader>
                    <TableRow>
                      <TableHead>SKU / Variant</TableHead>
                      <TableHead>Category</TableHead>
                      <TableHead>Stock Level</TableHead>
                      <TableHead>Unit Cost</TableHead>
                      <TableHead>Sale Price</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell className="font-mono font-medium">KB-MECH-BLK</TableCell>
                      <TableCell>Peripherals</TableCell>
                      <TableCell className="tabular-nums font-medium">{formatQuantity(145.0)} units</TableCell>
                      <TableCell className="tabular-nums">{formatCurrency(45.50)}</TableCell>
                      <TableCell className="tabular-nums font-semibold">{formatCurrency(89.99)}</TableCell>
                      <TableCell><StatusBadge status="completed" customLabel="In Stock" /></TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-mono font-medium">MS-WRLSS-GRY</TableCell>
                      <TableCell>Peripherals</TableCell>
                      <TableCell className="tabular-nums font-medium text-amber-600">{formatQuantity(4.0)} units</TableCell>
                      <TableCell className="tabular-nums">{formatCurrency(18.00)}</TableCell>
                      <TableCell className="tabular-nums font-semibold">{formatCurrency(35.00)}</TableCell>
                      <TableCell><StatusBadge status="low_stock" /></TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-mono font-medium">MON-4K-27IN</TableCell>
                      <TableCell>Displays</TableCell>
                      <TableCell className="tabular-nums font-medium text-red-600">{formatQuantity(0)} units</TableCell>
                      <TableCell className="tabular-nums">{formatCurrency(280.00)}</TableCell>
                      <TableCell className="tabular-nums font-semibold">{formatCurrency(449.99)}</TableCell>
                      <TableCell><StatusBadge status="out_of_stock" /></TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Alerts & Skeletons */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>System Callout Banners</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <Alert variant="info">
                <Info className="h-4 w-4" />
                <AlertTitle>Branch Scope Active</AlertTitle>
                <AlertDescription>Showing data restricted to Main Warehouse (Branch #1).</AlertDescription>
              </Alert>

              <Alert variant="warning">
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>Low Stock Threshold Exceeded</AlertTitle>
                <AlertDescription>2 items have fallen below their specified reorder point.</AlertDescription>
              </Alert>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Loading Skeleton State</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center space-x-4">
                <Skeleton className="h-12 w-12 rounded-full" />
                <div className="space-y-2 flex-1">
                  <Skeleton className="h-4 w-3/4" />
                  <Skeleton className="h-4 w-1/2" />
                </div>
              </div>
              <Skeleton className="h-20 w-full rounded-md" />
            </CardContent>
          </Card>
        </div>

        {/* Empty State Component */}
        <EmptyState
          title="No purchase orders found"
          description="You have not created any purchase orders for this branch yet."
          actionLabel="Create Purchase Order"
          onAction={() => toast.info("Create Purchase Order clicked")}
        />

        {/* Dialog Component */}
        <Dialog
          isOpen={isDialogOpen}
          onClose={() => setIsDialogOpen(false)}
          title="Confirm Stock Adjustment"
          description="Are you sure you want to log a manual stock adjustment for this variant?"
        >
          <div className="space-y-4 py-2">
            <p className="text-sm text-slate-600 dark:text-slate-300">
              This action will write an append-only stock movement entry and update the on-hand quantity.
            </p>
            <div className="flex justify-end gap-3 pt-2">
              <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                Cancel
              </Button>
              <Button onClick={() => {
                setIsDialogOpen(false);
                toast.success("Adjustment logged successfully");
              }}>
                Confirm Adjustment
              </Button>
            </div>
          </div>
        </Dialog>

        {/* Sheet Component (Drawer) */}
        <Sheet
          isOpen={isSheetOpen}
          onClose={() => setIsSheetOpen(false)}
          title="Line Item Details Drawer"
          description="Inspect audit ledger movement history."
        >
          <div className="space-y-4 py-4">
            <div className="rounded-md border border-slate-200 p-3 text-sm space-y-1 dark:border-slate-800">
              <div className="font-semibold text-slate-900 dark:text-slate-100">Movement #1042</div>
              <div className="text-slate-500">Type: Stock Adjustment In (+)</div>
              <div className="text-slate-500">Quantity Delta: +10.0000</div>
              <div className="text-slate-500">Reason: Physical Count Correction</div>
            </div>
            <Button className="w-full" onClick={() => setIsSheetOpen(false)}>
              Close Panel
            </Button>
          </div>
        </Sheet>

      </div>
    </main>
  );
}
