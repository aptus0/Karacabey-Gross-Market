import type { Metadata } from "next";
import type { ReactNode } from "react";
import Link from "next/link";
import { Clock, MapPin, MessageCircle, Phone, ShieldCheck, Store } from "lucide-react";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { fetchMaintenanceStatus } from "@/lib/maintenance";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Bakım Modu | Karacabey Gross Market",
  description: "Karacabey Gross Market kısa süreli bakım ekranı.",
  robots: { index: false, follow: false, nocache: true },
};

export default async function MaintenancePage() {
  const status = await fetchMaintenanceStatus();
  const title = status?.title ?? "Kısa bir bakım yapıyoruz";
  const message = status?.message ?? "Karacabey Gross Market deneyimini daha hızlı ve güvenli hale getirmek için kısa süreli bakımdayız.";
  const phone = status?.support?.phone ?? "(0224) 676 84 33";
  const whatsapp = status?.support?.whatsapp ?? "9065453458663";

  return (
    <main className="min-h-screen bg-[#fff7ed] px-4 py-8 text-slate-950 sm:px-6 lg:px-8">
      <section className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-5xl content-center gap-8">
        <div className="rounded-[2rem] border border-orange-200 bg-white p-6 shadow-sm sm:p-8 lg:p-10">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <Link href="/" className="inline-flex w-fit rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
              <KgmLogo />
            </Link>
            <span className="inline-flex w-fit items-center gap-2 rounded-full bg-orange-100 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700">
              <Clock className="h-4 w-4" /> Bakım Modu
            </span>
          </div>

          <div className="mt-10 grid gap-4">
            <p className="text-sm font-black uppercase tracking-[0.22em] text-orange-600">Karacabey Gross Market</p>
            <h1 className="max-w-3xl text-4xl font-black leading-[1.05] tracking-[-0.05em] text-slate-950 sm:text-5xl">
              {title}
            </h1>
            <p className="max-w-2xl text-base font-medium leading-8 text-slate-600 sm:text-lg">
              {message}
            </p>
          </div>

          <div className="mt-8 grid gap-3 sm:grid-cols-3">
            <InfoCard icon={<ShieldCheck className="h-5 w-5" />} title="Güvenli güncelleme" text="Sipariş ve ödeme güvenliği korunarak bakım uygulanıyor." />
            <InfoCard icon={<Store className="h-5 w-5" />} title="Mağaza bilgisi" text="Drama Mahallesi, Runguçpaşa Caddesi, Karacabey/Bursa." />
            <InfoCard icon={<MapPin className="h-5 w-5" />} title="Teslimat" text="Karacabey içi servis akışı bakım sonrası devam edecek." />
          </div>

          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <Link href={`tel:${phone.replace(/[^0-9+]/g, "")}`} className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-orange-600 px-5 text-sm font-black text-white transition hover:bg-orange-700">
              <Phone className="h-4 w-4" /> Telefonla Ara
            </Link>
            <Link href={`https://wa.me/${whatsapp}`} target="_blank" rel="noreferrer" className="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-orange-200 bg-white px-5 text-sm font-black text-orange-700 transition hover:bg-orange-50">
              <MessageCircle className="h-4 w-4" /> WhatsApp
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}

function InfoCard({ icon, title, text }: { icon: ReactNode; title: string; text: string }) {
  return (
    <div className="rounded-2xl border border-orange-100 bg-orange-50/60 p-4">
      <div className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-orange-600 shadow-sm">{icon}</div>
      <strong className="mt-3 block text-sm font-black text-slate-950">{title}</strong>
      <p className="mt-1 text-sm leading-6 text-slate-600">{text}</p>
    </div>
  );
}
