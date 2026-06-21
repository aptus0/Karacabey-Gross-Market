import type { Metadata } from "next";
import Link from "next/link";
import { CustomerCoupons } from "@/app/_components/CustomerCoupons";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Kuponlarım",
  description: "Karacabey Gross Market kuponları.",
  path: "/account/coupons",
  keywords: ["kuponlarım", "indirim kuponu"],
  robots: { index: false, follow: false },
});

export default function AccountCouponsPage() {
  return (
    <AppLayout>
      <section className="account-page-head">
        <div>
          <p className="eyebrow">Hesabım</p>
          <h1>Kuponlarım</h1>
        </div>
        <Link className="secondary-action" href="/kampanyalar">Kampanyalar</Link>
      </section>
      <section className="account-simple-card">
        <CustomerCoupons />
      </section>
    </AppLayout>
  );
}
