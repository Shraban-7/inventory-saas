"use client";

import * as React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useCustomersQuery, useCreateCustomerMutation } from "@/features/sales/api/sales-api";
import { customerSchema, CustomerFormValues } from "@/features/sales/schemas/sales-schemas";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Users, Plus, Mail, Phone, Building2 } from "lucide-react";
import { toast } from "sonner";

export default function CustomersPage() {
  const { data: response, isLoading, refetch } = useCustomersQuery();
  const createMutation = useCreateCustomerMutation();
  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const customers = response?.data || [];

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(customerSchema),
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      default_branch_id: undefined,
    },
  });

  const onSubmit = async (values: CustomerFormValues) => {
    setApiError(null);
    try {
      await createMutation.mutateAsync({
        name: values.name,
        email: values.email || null,
        phone: values.phone || null,
        default_branch_id: values.default_branch_id ? Number(values.default_branch_id) : null,
      });
      toast.success("Customer profile created successfully!");
      reset();
      setIsModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to create customer");
    }
  };

  return (
    <PermissionGuard permission="invoice.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Customers Directory"
          description="Manage client accounts, contact details, and branch associations."
          actions={
            <PermissionGuard permission="invoice.create">
              <Button onClick={() => setIsModalOpen(true)} className="gap-2 text-xs">
                <Plus className="h-4 w-4" /> Add Customer
              </Button>
            </PermissionGuard>
          }
        />

        {/* Customer Table */}
        <Card>
          <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Users className="h-4 w-4 text-teal-600" />
              Registered Accounts ({customers.length})
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>Customer Name</TableHead>
                  <TableHead>Email Address</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead>Default Branch</TableHead>
                  <TableHead>Registered Date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 4 }).map((_, idx) => (
                    <TableRow key={idx}>
                      <TableCell><Skeleton className="h-4 w-8" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-40" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                    </TableRow>
                  ))
                ) : customers.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="py-12">
                      <EmptyState
                        title="No customers registered"
                        description="Add customer profiles to start issuing sales invoices."
                        actionLabel="Add Customer"
                        onAction={() => setIsModalOpen(true)}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  customers.map((c) => (
                    <TableRow key={c.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{c.id}</TableCell>
                      <TableCell className="font-semibold text-slate-900 dark:text-slate-100">{c.name}</TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {c.email ? (
                          <span className="flex items-center gap-1"><Mail className="h-3 w-3" /> {c.email}</span>
                        ) : "—"}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {c.phone ? (
                          <span className="flex items-center gap-1"><Phone className="h-3 w-3" /> {c.phone}</span>
                        ) : "—"}
                      </TableCell>
                      <TableCell className="text-xs">
                        {c.default_branch_id ? (
                          <span className="flex items-center gap-1"><Building2 className="h-3 w-3" /> Branch #{c.default_branch_id}</span>
                        ) : "Global / Any"}
                      </TableCell>
                      <TableCell className="text-xs text-slate-500">
                        {c.created_at ? new Date(c.created_at).toLocaleDateString() : "—"}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Modal Form */}
        <Dialog
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          title="Register New Customer"
          description="Create a new client account for issuing invoices and tracking receivables."
        >
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-2">
            {apiError && (
              <div className="rounded-md bg-red-50 p-3 text-xs text-red-700 font-medium">
                {apiError.detail || apiError.title}
              </div>
            )}

            <div className="space-y-1">
              <Label required>Customer Name</Label>
              <Input {...register("name")} placeholder="Acme Logistics Inc." />
              {errors.name && <span className="text-xs text-red-500">{errors.name.message}</span>}
            </div>

            <div className="space-y-1">
              <Label>Email Address</Label>
              <Input type="email" {...register("email")} placeholder="billing@acme.com" />
              {errors.email && <span className="text-xs text-red-500">{errors.email.message}</span>}
            </div>

            <div className="space-y-1">
              <Label>Phone Number</Label>
              <Input {...register("phone")} placeholder="+1 (555) 019-2834" />
            </div>

            <div className="space-y-1">
              <Label>Default Branch ID (Optional)</Label>
              <Input type="number" {...register("default_branch_id")} placeholder="e.g. 1" />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={createMutation.isPending}>
                Save Customer Account
              </Button>
            </div>
          </form>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
