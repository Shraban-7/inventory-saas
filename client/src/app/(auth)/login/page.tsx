"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Package, ShieldAlert, UserCheck, ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { useAuthStore } from "@/lib/stores/auth-store";
import { RoleName } from "@/types/auth";
import { toast } from "sonner";

export default function LoginPage() {
  const router = useRouter();
  const { login, loginAsStubProfile, loginError, isLoading } = useAuthStore();
  const [email, setEmail] = React.useState("admin@inventory-saas.com");
  const [password, setPassword] = React.useState("password");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const success = await login(email, password);
    if (success) {
      toast.success("Login successful!");
      router.push("/dashboard");
    }
  };

  const handleStubLogin = (role: RoleName) => {
    loginAsStubProfile(role);
    toast.success(`Logged in as dev profile: ${role}`);
    router.push("/dashboard");
  };

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 p-4 dark:bg-slate-950">
      <div className="w-full max-w-md space-y-6">
        
        {/* Back to Home Link */}
        <div className="flex justify-start">
          <Link
            href="/"
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-teal-600 dark:text-slate-400 dark:hover:text-teal-400 transition-colors"
          >
            <ArrowLeft className="h-3.5 w-3.5" /> Back to Home
          </Link>
        </div>

        {/* Brand Header (Clickable Logo Redirecting to Landing Page /) */}
        <Link href="/" className="flex flex-col items-center text-center space-y-2 group cursor-pointer">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-600 text-white shadow-md group-hover:scale-105 transition-transform">
            <Package className="h-7 w-7" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
            OmniInventory SaaS Portal
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Enterprise Multi-Tenant REST API v1 Management Console
          </p>
        </Link>

        {/* Login Card */}
        <Card className="border-slate-200 shadow-lg dark:border-slate-800">
          <CardHeader>
            <CardTitle className="text-lg">Sign In to Your Workspace</CardTitle>
            <CardDescription>
              Enter your corporate credentials or choose a dev profile below.
            </CardDescription>
          </CardHeader>
          <form onSubmit={handleSubmit}>
            <CardContent className="space-y-4">
              {loginError && (
                <Alert variant="destructive">
                  <ShieldAlert className="h-4 w-4" />
                  <AlertTitle>{loginError.title}</AlertTitle>
                  <AlertDescription>{loginError.detail}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-2">
                <Label required>Work Email Address</Label>
                <Input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@company.com"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label required>Password</Label>
                <Input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                />
              </div>
            </CardContent>

            <CardFooter className="flex flex-col space-y-4">
              <Button type="submit" className="w-full" isLoading={isLoading}>
                Sign In with Credentials
              </Button>

              {/* Dev Stub Quick Presets */}
              <div className="w-full pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-center flex items-center justify-center gap-1">
                  <UserCheck className="h-3 w-3" /> Dev Profile Presets
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => handleStubLogin("Admin")}
                  >
                    Admin
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => handleStubLogin("Manager")}
                  >
                    Manager
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => handleStubLogin("Cashier")}
                  >
                    Cashier
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => handleStubLogin("Accountant")}
                  >
                    Accountant
                  </Button>
                </div>
              </div>
            </CardFooter>
          </form>
        </Card>

      </div>
    </main>
  );
}
