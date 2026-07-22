import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from "axios";

/**
 * RFC 7807 Problem Details Response Structure
 */
export interface ProblemDetailsError {
  code: string;
  message: string;
}

export interface ProblemDetails {
  type: string;
  title: string;
  status: number;
  detail: string;
  instance?: string;
  errors?: Record<string, ProblemDetailsError[] | string[]>;
}

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8000/api/v1";

/**
 * Custom Axios instance configured for Inventory SaaS REST API (v1).
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "Accept": "application/json",
    "Content-Type": "application/json",
  },
  withCredentials: true, // Support Laravel Sanctum cookie/session authentication
});

/**
 * Generates a fresh UUIDv4 for Idempotency-Key headers.
 */
export function generateIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  // Fallback UUIDv4 generator
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

// Request Interceptor: Attach Bearer Token & Idempotency Key
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Attach Bearer Token from localStorage if present
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("inventory_auth_token");
      if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }

    // Automatically inject Idempotency-Key for state-modifying requests (POST, PUT, PATCH, DELETE)
    const isMutation = ["post", "put", "patch", "delete"].includes(config.method?.toLowerCase() || "");
    if (isMutation && config.headers && !config.headers["Idempotency-Key"]) {
      config.headers["Idempotency-Key"] = generateIdempotencyKey();
    }

    return config;
  },
  (error) => Promise.reject(error)
);

/**
 * Parses any Axios or network error into a normalized RFC 7807 ProblemDetails object.
 */
export function parseProblemDetails(error: unknown): ProblemDetails {
  if (axios.isAxiosError(error)) {
    const axiosError = error as AxiosError<ProblemDetails>;
    if (axiosError.response?.data && typeof axiosError.response.data === "object" && axiosError.response.data.title) {
      return axiosError.response.data;
    }
    
    return {
      type: "urn:problem:http",
      title: axiosError.response?.statusText || "HTTP Error",
      status: axiosError.response?.status || 500,
      detail: axiosError.message || "An error occurred while communicating with the server.",
      errors: {},
    };
  }

  return {
    type: "urn:problem:generic",
    title: "Unexpected Error",
    status: 500,
    detail: error instanceof Error ? error.message : "An unexpected error occurred.",
    errors: {},
  };
}
