export type RoleName = "Admin" | "Manager" | "Cashier" | "Accountant" | string;

export interface User {
  id: number;
  name: string;
  email: string;
  tenant_id: number;
  created_at?: string;
}

export interface Branch {
  id: number;
  tenant_id: number;
  name: string;
  code?: string;
  address?: string;
  is_active?: boolean;
}

export interface AuthSession {
  user: User | null;
  roles: RoleName[];
  permissions: string[];
  branches: Branch[];
  token: string | null;
}

export interface LoginResponse {
  token: string;
  user: User;
  roles: RoleName[];
  permissions: string[];
  branches: Branch[];
}
