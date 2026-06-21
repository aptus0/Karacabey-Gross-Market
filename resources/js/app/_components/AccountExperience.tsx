"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { useEffect, useMemo, useState } from "react";
import { Bell, Heart, LogOut, MapPin, Package, RefreshCw, Settings, ShoppingBag, Ticket } from "lucide-react";
import { useRouter } from "next/navigation";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { useAuthStore } from "@/lib/auth-store";
import {
  fetchCustomerDashboard,
  fetchUserAddresses,
  fetchUserOrders,
  formatCartMoney,
  type CustomerDashboard,
  type UserAddress,
  type UserOrder,
} from "@/lib/account";

const quickLinks = [
  { href: "/account/orders", label: "Siparişler", icon: Package },
  { href: "/account/addresses", label: "Adresler", icon: MapPin },
  { href: "/favorites", label: "Favoriler", icon: Heart },
  { href: "/account/coupons", label: "Kuponlar", icon: Ticket },
  { href: "/notifications", label: "Bildirimler", icon: Bell },
  { href: "/account/settings", label: "Ayarlar", icon: Settings },
];

export function AccountExperience() {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const logout = useAuthStore((state) => state.logout);

  const [dashboard, setDashboard] = useState<CustomerDashboard | null>(null);
  const [orders, setOrders] = useState<UserOrder[]>([]);
  const [addresses, setAddresses] = useState<UserAddress[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshKey, setRefreshKey] = useState(0);

  useEffect(() => {
    if (!isHydrated) return;
    if (!isAuthenticated) router.replace("/auth/login");
  }, [isAuthenticated, isHydrated, router]);

  useEffect(() => {
    if (!isHydrated || !token) return;

    let active = true;
    setLoading(true);
    Promise.allSettled([fetchCustomerDashboard(token), fetchUserOrders(token), fetchUserAddresses(token)])
      .then(([dashboardRes, ordersRes, addressesRes]) => {
        if (!active) return;
        if (dashboardRes.status === "fulfilled") setDashboard(dashboardRes.value);
        if (ordersRes.status === "fulfilled") setOrders(ordersRes.value.data ?? []);
        if (addressesRes.status === "fulfilled") setAddresses(addressesRes.value);
        setError(dashboardRes.status === "rejected" && ordersRes.status === "rejected" ? "Hesap bilgileri alınamadı." : null);
      })
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, [token, isHydrated, refreshKey]);

  const summary = useMemo(() => {
    const dashboardSummary = dashboard?.summary;
    return {
      ordersTotal: dashboardSummary?.orders_total ?? orders.length,
      addressesTotal: dashboardSummary?.addresses_total ?? addresses.length,
      favoritesTotal: dashboardSummary?.favorites_total ?? 0,
      unreadNotifications: dashboardSummary?.unread_notifications ?? 0,
      cartItemsCount: dashboardSummary?.cart_items_count ?? 0,
      cartTotalCents: dashboardSummary?.cart_total_cents ?? 0,
    };
  }, [addresses.length, dashboard?.summary, orders]);

  async function handleLogout() {
    await logout();
    router.replace("/");
  }

  if (!isHydrated || !isAuthenticated) return null;

  return (
    <AppLayout>
      <section className="account-page-head">
        <div>
          <p className="eyebrow">Hesabım</p>
          <h1>{user?.name || "Müşteri"}</h1>
        </div>
        <button type="button" onClick={() => void handleLogout()} className="account-logout-inline">
          <LogOut size={15} /> Çıkış
        </button>
      </section>

      {error ? (
        <div className="customer-alert customer-alert--danger">
          <span>{error}</span>
          <button type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={14} /> Yenile</button>
        </div>
      ) : null}

      <section className="account-metric-grid">
        <AccountMetric icon={<Package size={18} />} label="Sipariş" value={summary.ordersTotal} />
        <AccountMetric icon={<MapPin size={18} />} label="Adres" value={summary.addressesTotal} />
        <AccountMetric icon={<Heart size={18} />} label="Favori" value={summary.favoritesTotal} />
        <AccountMetric icon={<Bell size={18} />} label="Bildirim" value={summary.unreadNotifications} />
      </section>

      <section className="account-simple-card">
        <div className="account-simple-card__head">
          <h2>İşlemler</h2>
          <Link href="/products">Alışverişe dön</Link>
        </div>
        <div className="account-action-grid">
          {quickLinks.map((item) => {
            const Icon = item.icon;
            return (
              <Link key={item.href} href={item.href} className="account-action-tile">
                <Icon size={18} />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </div>
      </section>

      <section className="account-simple-grid">
        <div className="account-simple-card">
          <div className="account-simple-card__head">
            <h2>Son Siparişler</h2>
            <Link href="/account/orders">Tümü</Link>
          </div>
          {loading ? (
            <div className="account-empty">Yükleniyor...</div>
          ) : orders.length === 0 ? (
            <div className="account-empty">Henüz sipariş yok.</div>
          ) : (
            <div className="account-order-list-mini">
              {orders.slice(0, 4).map((order) => (
                <Link key={order.id} href="/account/orders" className="account-order-mini-row">
                  <span>{order.status_label}</span>
                  <strong>{formatCartMoney(order.total_cents)}</strong>
                </Link>
              ))}
            </div>
          )}
        </div>

        <div className="account-simple-card">
          <div className="account-simple-card__head">
            <h2>Sepet</h2>
            <Link href="/sepet">Aç</Link>
          </div>
          <div className="account-cart-mini">
            <ShoppingBag size={18} />
            <span>{summary.cartItemsCount} ürün</span>
            <strong>{formatCartMoney(summary.cartTotalCents)}</strong>
          </div>
        </div>
      </section>
    </AppLayout>
  );
}

function AccountMetric({ icon, label, value }: { icon: ReactNode; label: string; value: number }) {
  return (
    <article className="account-metric-card">
      <span>{icon}</span>
      <div>
        <strong>{value}</strong>
        <small>{label}</small>
      </div>
    </article>
  );
}
