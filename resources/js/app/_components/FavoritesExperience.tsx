"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { FavoritesList } from "@/app/_components/FavoritesList";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { useAuthStore } from "@/lib/auth-store";

export function FavoritesExperience() {
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
      <section className="account-heading account-heading--customer">
        <div>
          <p className="eyebrow">Kayıtlı Ürünler</p>
          <h1>Favoriler</h1>
          <p>Web ve mobil arasında senkron çalışan tekrar alışveriş listeniz.</p>
        </div>
        <Link className="secondary-action" href="/products">Alışverişe Dön</Link>
      </section>

      <section className="customer-panel-card">
        <div className="customer-panel-card__heading">
          <div>
            <p className="eyebrow">Hazır Liste</p>
            <h2>Tekrar almak isteyebileceğin ürünler</h2>
          </div>
        </div>
        <FavoritesList />
      </section>
    </AppLayout>
  );
}
