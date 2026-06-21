"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { NotificationsCenter } from "@/app/_components/NotificationsCenter";
import { useAuthStore } from "@/lib/auth-store";

export function NotificationsExperience() {
  const router = useRouter();
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  useEffect(() => {
    if (!isHydrated) return;
    if (!isAuthenticated) router.replace("/auth/login");
  }, [isAuthenticated, isHydrated, router]);

  if (!isHydrated || !isAuthenticated) return null;

  return (
    <AppLayout sidebar>
      <NotificationsCenter />
    </AppLayout>
  );
}
