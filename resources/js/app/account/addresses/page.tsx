import type { Metadata } from "next";
import Link from "next/link";
import { AccountAddresses } from "@/app/_components/AccountAddresses";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Adreslerim",
  description: "Karacabey Gross Market teslimat adresleri.",
  path: "/account/addresses",
  keywords: ["adreslerim", "teslimat adresi"],
  robots: { index: false, follow: false },
});

export default function AccountAddressesPage() {
  return (
    <AppLayout>
      <section className="account-page-head">
        <div>
          <p className="eyebrow">Hesabım</p>
          <h1>Adreslerim</h1>
        </div>
        <Link className="secondary-action" href="/account">Hesabım</Link>
      </section>
      <section className="account-simple-card">
        <AccountAddresses />
      </section>
    </AppLayout>
  );
}
