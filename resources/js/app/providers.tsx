"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { AppBootstrap } from "@/app/_components/AppBootstrap";
import { ChunkRecovery } from "@/app/_components/ChunkRecovery";
import { CustomerSyncBridge } from "@/app/_components/CustomerSyncBridge";

type ProvidersProps = {
  children: ReactNode;
};

export function Providers({ children }: ProvidersProps) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            refetchOnWindowFocus: false,
            retry: 1,
            staleTime: 60_000,
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <ChunkRecovery />
      <AppBootstrap />
      <CustomerSyncBridge />
    </QueryClientProvider>
  );
}
