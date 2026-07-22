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
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useSuppliersQuery,
  useCreateSupplierMutation,
  useUpdateSupplierMutation,
  mutationErrorToast,
} from "@/features/purchasing/api/purchasing-api";
import { supplierSchema, SupplierFormValues } from "@/features/purchasing/schemas/purchasing-schemas";
import { Supplier } from "@/types/purchasing";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Building, Plus, Mail, Phone, UserCheck, Pencil, ChevronLeft, ChevronRight, ShieldAlert } from "lucide-react";
import { toast } from "sonner";

export default function SuppliersPage() {
  const [page, setPage] = React.useState(1);
  const { data: response, isLoading, isError, error, refetch } = useSuppliersQuery({
    page,
    per_page: 25,
  });
  const createMutation = useCreateSupplierMutation();
  const updateMutation = useUpdateSupplierMutation();

  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<Supplier | null>(null);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const suppliers = response?.data || [];
  const meta = response?.meta;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(supplierSchema),
    defaultValues: {
      name: "",
      contact_name: "",
      email: "",
      phone: "",
    },
  });

  const openCreate = () => {
    setEditing(null);
    setApiError(null);
    reset({ name: "", contact_name: "", email: "", phone: "" });
    setIsModalOpen(true);
  };

  const openEdit = (supplier: Supplier) => {
    setEditing(supplier);
    setApiError(null);
    reset({
      name: supplier.name,
      contact_name: supplier.contact_name || "",
      email: supplier.email || "",
      phone: supplier.phone || "",
    });
    setIsModalOpen(true);
  };

  const onSubmit = async (values: SupplierFormValues) => {
    setApiError(null);
    const payload = {
      name: values.name,
      contact_name: values.contact_name || null,
      email: values.email || null,
      phone: values.phone || null,
    };

    try {
      if (editing) {
        await updateMutation.mutateAsync({ id: editing.id, payload });
        toast.success("Supplier updated.");
      } else {
        await createMutation.mutateAsync(payload);
        toast.success("Supplier registered.");
      }
      reset();
      setIsModalOpen(false);
      setEditing(null);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(mutationErrorToast(problem));
    }
  };

  return (
    <PermissionGuard permission="purchase.create" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Vendors & Suppliers"
          description="Supplier master directory for purchase orders, GRNs, and vendor bills."
          actions={
            <Button onClick={openCreate} className="gap-2 text-xs">
              <Plus className="h-4 w-4" /> Add Supplier
            </Button>
          }
        />

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load suppliers</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Building className="h-4 w-4 text-teal-600" />
              Registered Vendors {meta ? `(${meta.total})` : ""}
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>Company</TableHead>
                  <TableHead>Contact</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead>Registered</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 4 }).map((_, idx) => (
                    <TableRow key={idx}>
                      {Array.from({ length: 7 }).map((__, c) => (
                        <TableCell key={c}>
                          <Skeleton className="h-4 w-20" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : suppliers.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="py-12">
                      <EmptyState
                        title="No suppliers registered"
                        description="Add vendor profiles before issuing purchase orders."
                        actionLabel="Add Supplier"
                        onAction={openCreate}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  suppliers.map((s) => (
                    <TableRow key={s.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{s.id}</TableCell>
                      <TableCell className="font-semibold text-slate-900 dark:text-slate-100">
                        {s.name}
                      </TableCell>
                      <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                        {s.contact_name ? (
                          <span className="flex items-center gap-1">
                            <UserCheck className="h-3 w-3" /> {s.contact_name}
                          </span>
                        ) : (
                          "—"
                        )}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {s.email ? (
                          <span className="flex items-center gap-1">
                            <Mail className="h-3 w-3" /> {s.email}
                          </span>
                        ) : (
                          "—"
                        )}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {s.phone ? (
                          <span className="flex items-center gap-1">
                            <Phone className="h-3 w-3" /> {s.phone}
                          </span>
                        ) : (
                          "—"
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-slate-500">
                        {s.created_at ? new Date(s.created_at).toLocaleDateString() : "—"}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          variant="ghost"
                          className="gap-1 text-xs"
                          onClick={() => openEdit(s)}
                        >
                          <Pencil className="h-3.5 w-3.5" /> Edit
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>

            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span className="text-xs text-slate-500">
                  Page {meta.current_page} of {meta.last_page}
                </span>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    className="gap-1 text-xs"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" /> Previous
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => setPage((p) => p + 1)}
                    className="gap-1 text-xs"
                  >
                    Next <ChevronRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Dialog
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          title={editing ? `Edit Supplier #${editing.id}` : "Register New Supplier"}
          description="Supplier fields match SupplierRequest (name, contact, email, phone)."
        >
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-2">
            {apiError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{apiError.title}</AlertTitle>
                <AlertDescription>{apiError.detail}</AlertDescription>
              </Alert>
            )}

            <div className="space-y-1">
              <Label required>Supplier Company Name</Label>
              <Input {...register("name")} placeholder="Global Logistics Corp" />
              {errors.name && <span className="text-xs text-red-500">{errors.name.message}</span>}
            </div>

            <div className="space-y-1">
              <Label>Contact Name</Label>
              <Input {...register("contact_name")} placeholder="Jane Doe" />
            </div>

            <div className="space-y-1">
              <Label>Email</Label>
              <Input type="email" {...register("email")} placeholder="orders@example.com" />
              {errors.email && <span className="text-xs text-red-500">{errors.email.message}</span>}
            </div>

            <div className="space-y-1">
              <Label>Phone</Label>
              <Input {...register("phone")} placeholder="+1 800 555 0199" />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                isLoading={createMutation.isPending || updateMutation.isPending}
              >
                {editing ? "Save Changes" : "Save Supplier"}
              </Button>
            </div>
          </form>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
