import { create } from "zustand";
import { AuthSession, Branch, RoleName } from "@/types/auth";
import { apiClient, parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { useShellStore } from "./shell-store";

interface AuthState extends AuthSession {
  isAuthenticated: boolean;
  isLoading: boolean;
  loginError: ProblemDetails | null;
  login: (email: string, password?: string) => Promise<boolean>;
  loginAsStubProfile: (role: RoleName) => void;
  logout: () => void;
  hasPermission: (permission: string) => boolean;
  hasRole: (role: RoleName) => boolean;
}

// System stub branches for dev mode
const SYSTEM_DEV_BRANCHES: Branch[] = [
  { id: 1, tenant_id: 1, name: "Main Warehouse (Branch #1)", code: "WH-01" },
  { id: 2, tenant_id: 1, name: "Downtown Retail Store (Branch #2)", code: "RET-02" },
];

const DEFAULT_ADMIN_PERMISSIONS = [
  "invoice.view",
  "invoice.create",
  "invoice.void",
  "product.manage",
  "stock.adjust",
  "stock.transfer",
  "purchase.create",
  "purchase.receive",
  "report.view",
];

// Helper to inspect initial token from storage
const getStoredToken = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("inventory_auth_token") || "dev-stub-token";
  }
  return "dev-stub-token";
};

const hasInitialSession = typeof window !== "undefined" ? !!localStorage.getItem("inventory_auth_token") : true;

export const useAuthStore = create<AuthState>((set, get) => ({
  user: hasInitialSession ? {
    id: 1,
    name: "System Administrator",
    email: "admin@inventory-saas.com",
    tenant_id: 1,
  } : null,
  roles: hasInitialSession ? ["Admin"] : [],
  permissions: hasInitialSession ? DEFAULT_ADMIN_PERMISSIONS : [],
  branches: hasInitialSession ? SYSTEM_DEV_BRANCHES : [],
  token: getStoredToken(),
  isAuthenticated: hasInitialSession,
  isLoading: false,
  loginError: null,

  login: async (email, password) => {
    set({ isLoading: true, loginError: null });
    try {
      // Attempt real backend authentication
      const response = await apiClient.post("/login", { email, password });
      const data = response.data;
      
      const token = data.token || "session-token-" + Date.now();
      if (typeof window !== "undefined") {
        localStorage.setItem("inventory_auth_token", token);
      }

      const branches = data.branches && data.branches.length > 0 ? data.branches : SYSTEM_DEV_BRANCHES;
      
      set({
        user: data.user,
        roles: data.roles || [],
        permissions: data.permissions || [],
        branches,
        token,
        isAuthenticated: true,
        isLoading: false,
      });

      if (branches.length > 0) {
        useShellStore.getState().setActiveBranchId(branches[0].id);
      }

      return true;
    } catch (err: unknown) {
      // Fallback for dev mode when backend login endpoint is pending
      const problem = parseProblemDetails(err);
      set({ loginError: problem, isLoading: false });
      return false;
    }
  },

  loginAsStubProfile: (roleName: RoleName) => {
    let permissions: string[] = [];
    let name = "Dev User";

    switch (roleName) {
      case "Admin":
        name = "Alex Admin";
        permissions = DEFAULT_ADMIN_PERMISSIONS;
        break;
      case "Manager":
        name = "Morgan Manager";
        permissions = [
          "invoice.create",
          "invoice.view",
          "report.view",
          "stock.adjust",
          "stock.transfer",
          "product.manage",
          "purchase.create",
          "purchase.receive",
        ];
        break;
      case "Cashier":
        name = "Casey Cashier";
        permissions = ["invoice.create", "invoice.view"];
        break;
      case "Accountant":
        name = "Avery Accountant";
        permissions = ["invoice.view", "report.view"];
        break;
      default:
        name = "Dev User";
        permissions = [];
        break;
    }

    const token = "dev-stub-token-" + roleName.toLowerCase();
    if (typeof window !== "undefined") {
      localStorage.setItem("inventory_auth_token", token);
    }

    set({
      user: { id: 2, name, email: `${roleName.toLowerCase().replace(/\s+/g, "")}@saas.com`, tenant_id: 1 },
      roles: [roleName],
      permissions,
      branches: SYSTEM_DEV_BRANCHES,
      token,
      isAuthenticated: true,
      loginError: null,
    });

    useShellStore.getState().setActiveBranchId(SYSTEM_DEV_BRANCHES[0].id);
  },

  logout: () => {
    if (typeof window !== "undefined") {
      localStorage.removeItem("inventory_auth_token");
    }
    set({
      user: null,
      roles: [],
      permissions: [],
      branches: [],
      token: null,
      isAuthenticated: false,
    });
  },

  hasPermission: (perm: string) => {
    const { roles, permissions } = get();
    if (roles.includes("Admin")) return true;
    return permissions.includes(perm);
  },

  hasRole: (role: RoleName) => {
    return get().roles.includes(role);
  },
}));
