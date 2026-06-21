import type { Metadata } from "next";
import Link from "next/link";
import { AccountAddresses } from "@/app/_components/AccountAddresses";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Adreslerim",
  description: "Karacabey Gross Market kayıtlı teslimat adresleri ekranı.",
  path: "/addresses",
  keywords: ["adreslerim", "teslimat adresi", "kayıtlı konumlar"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function AddressesPage() {
  return (
    <AppLayout>
      <section className="account-heading">
        <div>
          <p className="eyebrow">Teslimat Noktaları</p>
          <h1>Adreslerim</h1>
        </div>
        <Link className="secondary-action" href="/account/addresses">
          Hesap Adreslerine Git
        </Link>
      </section>

      <section className="content-band content-band--narrow">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Kayıtlı Adresler</p>
            <h2>Hızlı teslimat için hazır konumlar</h2>
          </div>
        </div>
        <AccountAddresses />
      </section>
    </AppLayout>
  );
}
