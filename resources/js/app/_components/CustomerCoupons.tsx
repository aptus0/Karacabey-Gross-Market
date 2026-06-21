"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Copy, Loader2, TicketPercent } from "lucide-react";
import { fetchCustomerCoupons, formatCartMoney, type CustomerCoupon } from "@/lib/account";
import { useAuthStore } from "@/lib/auth-store";

export function CustomerCoupons() {
  const token = useAuthStore((state) => state.token);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const [coupons, setCoupons] = useState<CustomerCoupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState<string | null>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!token) {
      setLoading(false);
      return;
    }

    fetchCustomerCoupons(token)
      .then(setCoupons)
      .catch(() => setCoupons([]))
      .finally(() => setLoading(false));
  }, [token, isHydrated]);

  async function copyCode(code: string) {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(code);
      window.setTimeout(() => setCopied(null), 1500);
    } catch {
      setCopied(null);
    }
  }

  if (loading) {
    return (
      <div className="customer-empty-state">
        <Loader2 size={22} className="animate-spin" />
        Kuponlar yükleniyor...
      </div>
    );
  }

  if (coupons.length === 0) {
    return (
      <div className="customer-empty-state">
        <TicketPercent size={36} />
        <strong>Aktif kupon görünmüyor</strong>
        <p>Kişiye özel kampanyalar ve sepet kuponları burada listelenecek.</p>
        <Link href="/kampanyalar" className="primary-action">Kampanyaları İncele</Link>
      </div>
    );
  }

  return (
    <div className="customer-coupon-grid">
      {coupons.map((coupon) => (
        <article key={coupon.id} className="customer-coupon-card">
          <div className="customer-coupon-card__badge">
            <TicketPercent size={20} />
            {coupon.discount_type === "percent" ? `%${coupon.discount_value}` : formatCartMoney(coupon.discount_value)}
          </div>
          <div>
            <p className="eyebrow">Aktif Kupon</p>
            <h2>{coupon.code}</h2>
            <p>
              Minimum sepet: <strong>{formatCartMoney(coupon.minimum_order_cents)}</strong>
            </p>
          </div>
          <div className="customer-coupon-card__footer">
            <span>{coupon.ends_at ? `Son: ${new Intl.DateTimeFormat("tr-TR", { day: "numeric", month: "short" }).format(new Date(coupon.ends_at))}` : "Süresiz"}</span>
            <button type="button" onClick={() => copyCode(coupon.code)}>
              <Copy size={15} /> {copied === coupon.code ? "Kopyalandı" : "Kodu Kopyala"}
            </button>
          </div>
        </article>
      ))}
    </div>
  );
}
