import type { Metadata } from "next";
import Link from "next/link";
import { AlertTriangle, Home, Search } from "lucide-react";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Sayfa Bulunamadı",
  description: "Aradığınız sayfa taşınmış veya kaldırılmış olabilir.",
  path: "/404",
  robots: { index: false, follow: true },
});

export default function NotFound() {
  return (
    <GuestLayout>
      <main className="kgm-error-page">
        <section className="kgm-error-surface">
          <p className="eyebrow">404</p>
          <div className="kgm-error-icon kgm-error-icon--warning">
            <AlertTriangle size={28} />
          </div>
          <h1>Bu sayfa reyonda yok</h1>
          <p>
            Aradığınız bağlantı taşınmış veya kaldırılmış olabilir. Ana sayfaya dönebilir ya da ürün
            kataloğunda hızlıca arama yapabilirsiniz.
          </p>
          <div className="kgm-error-actions">
            <Link href="/" className="primary-action">
              <Home size={16} />
              Ana Sayfa
            </Link>
            <Link href="/products" className="secondary-action">
              <Search size={16} />
              Ürünleri İncele
            </Link>
          </div>
        </section>
      </main>
    </GuestLayout>
  );
}
