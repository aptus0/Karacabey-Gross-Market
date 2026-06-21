import type { Metadata } from "next";
import Link from "next/link";
import { AccountOrders } from "@/app/_components/AccountOrders";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Siparişlerim",
  description: "Karacabey Gross Market sipariş geçmişi.",
  path: "/account/orders",
  keywords: ["siparişlerim", "sipariş geçmişi"],
  robots: { index: false, follow: false },
});

export default function AccountOrdersPage() {
  return (
    <AppLayout>
      <section className="account-page-head">
        <div>
          <p className="eyebrow">Hesabım</p>
          <h1>Siparişlerim</h1>
        </div>
        <Link className="secondary-action" href="/account">Hesabım</Link>
      </section>
      <section className="account-simple-card">
        <AccountOrders />
      </section>
    </AppLayout>
  );
}
