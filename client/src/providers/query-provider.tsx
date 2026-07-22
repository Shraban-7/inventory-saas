"use client";

import * as React from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import axios from "axios";

export function QueryProvider({ children }: { children: React.ReactNode }) {
  const [queryClient] = React.useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 10 * 1000, // 10 seconds stale time for operational SaaS data
            refetchOnWindowFocus: false,
            retry: (failureCount, error: unknown) => {
              // Don't retry on 401, 403, 404, or 422 errors
              if (axios.isAxiosError(error)) {
                const status = error.response?.status;
                if (status && [401, 403, 404, 422].includes(status)) return false;
              }
              return failureCount < 2;
            },
          },
        },
      })
  );

  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
}
