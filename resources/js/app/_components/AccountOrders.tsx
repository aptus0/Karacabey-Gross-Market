"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Loader2, PackageX } from "lucide-react";
import { OrderCard } from "@/app/_components/OrderCard";
import { fetchUserOrders, type UserOrder } from "@/lib/account";
import { useAuthStore } from "@/lib/auth-store";
import { cn } from "@/lib/utils";

const filters = [
  { label: "Tümü", value: "all" },
  { label: "Aktif", value: "active" },
  { label: "Teslim", value: "delivered" },
  { label: "Sorun", value: "issue" },
];

export function AccountOrders() {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const [orders, setOrders] = useState<UserOrder[]>([]);
  const [activeFilter, setActiveFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!token) {
      router.replace("/auth/login");
      return;
    }

    setLoading(true);
    fetchUserOrders(token)
      .then((res) => setOrders(res.data ?? []))
      .catch(() => setError("Siparişler yüklenemedi."))
      .finally(() => setLoading(false));
  }, [token, isHydrated, router]);

  const filteredOrders = useMemo(() => {
    if (activeFilter === "active") return orders.filter((order) => ["awaiting_payment", "reviewing", "paid", "preparing", "shipping", "in_delivery"].includes(order.status));
    if (activeFilter === "delivered") return orders.filter((order) => ["completed", "delivered"].includes(order.status));
    if (activeFilter === "issue") return orders.filter((order) => ["failed", "cancelled", "refunded"].includes(order.status));
    return orders;
  }, [activeFilter, orders]);

  if (loading) {
    return <div className="account-empty"><Loader2 size={18} className="animate-spin" /> Siparişler yükleniyor...</div>;
  }

  if (error) return <p className="py-4 text-sm text-[#DC2626]">{error}</p>;

  if (orders.length === 0) {
    return (
      <div className="account-empty">
        <PackageX size={22} />
        Henüz sipariş yok.
      </div>
    );
  }

  return (
    <div className="max-w-4xl">
      <div className="sm:hidden mb-4">
        <label htmlFor="tabs" className="sr-only">Filtre Seç</label>
        <select
          id="tabs"
          name="tabs"
          className="block w-full rounded-md border-slate-300 py-2 pl-3 pr-10 text-base focus:border-orange-500 focus:outline-none focus:ring-orange-500 sm:text-sm"
          value={activeFilter}
          onChange={(e) => setActiveFilter(e.target.value)}
        >
          {filters.map((filter) => (
            <option key={filter.value} value={filter.value}>{filter.label}</option>
          ))}
        </select>
      </div>
      <div className="hidden sm:block mb-8">
        <div className="border-b border-slate-200">
          <nav className="-mb-px flex space-x-8" aria-label="Tabs">
            {filters.map((filter) => (
              <button
                key={filter.value}
                onClick={() => setActiveFilter(filter.value)}
                className={cn(
                  activeFilter === filter.value
                    ? "border-orange-500 text-orange-600"
                    : "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700",
                  "whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                )}
                aria-current={activeFilter === filter.value ? "page" : undefined}
              >
                {filter.label}
              </button>
            ))}
          </nav>
        </div>
      </div>
      
      <div className="space-y-6">
        {filteredOrders.map((order) => <OrderCard key={order.id} order={order} />)}
      </div>
    </div>
  );
}
