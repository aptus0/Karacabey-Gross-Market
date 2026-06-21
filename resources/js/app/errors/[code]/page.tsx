import type { Metadata } from "next";
import Link from "next/link";
import { AlertCircle, Home, Search } from "lucide-react";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { getHttpStatusMeta, supportedHttpStatusCodes } from "@/app/_lib/httpStatusCatalog";
import { buildMetadata } from "@/lib/seo";

export const dynamic = "force-dynamic";

type ErrorStatusPageProps = {
  params: Promise<{ code: string }>;
};

export function generateStaticParams() {
  return supportedHttpStatusCodes.map((code) => ({ code: String(code) }));
}

export async function generateMetadata({ params }: ErrorStatusPageProps): Promise<Metadata> {
  const { code: rawCode } = await params;
  const code = Number(rawCode);
  const meta = getHttpStatusMeta(Number.isFinite(code) ? code : 500);

  return buildMetadata({
    title: `${meta.code} ${meta.title}`,
    description: meta.description,
    path: `/errors/${meta.code}`,
    robots: { index: false, follow: false },
  });
}

function makeUid(prefix: string, code: number) {
  return `${prefix}_${code}_${Math.random().toString(16).slice(2, 10)}`;
}

export default async function ErrorStatusPage({ params }: ErrorStatusPageProps) {
  const { code: rawCode } = await params;
  const code = Number(rawCode);
  const meta = getHttpStatusMeta(Number.isFinite(code) ? code : 500);
  const errorUid = makeUid("kgm_err", meta.code);
  const requestUid = makeUid("kgm_req", meta.code);

  return (
    <GuestLayout>
      <main className="mx-auto min-h-[70vh] w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <section className="grid overflow-hidden rounded-lg border border-[#E5E7EB] bg-white shadow-[0_20px_56px_rgba(17,24,39,0.10)] lg:grid-cols-[1.1fr_340px]">
          <div className="p-7 sm:p-10">
            <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-[#FED7AA] bg-[#FFF7ED] px-3 py-1.5 text-xs font-extrabold uppercase tracking-[0.14em] text-[#EA580C]">
              <AlertCircle size={15} /> {meta.category}
            </div>
            <p className="text-7xl font-black leading-none tracking-normal text-[#111827] sm:text-8xl">{meta.code}</p>
            <h1 className="mt-4 max-w-md text-3xl font-black tracking-normal text-[#111827] sm:text-4xl">{meta.title}</h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-[#6B7280] sm:text-base">{meta.description}</p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Link href="/" className="inline-flex h-11 items-center gap-2 rounded-lg bg-[#FF5A00] px-4 text-sm font-extrabold text-white">
                <Home size={16} /> Ana Sayfa
              </Link>
              <Link href="/products" className="inline-flex h-11 items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-4 text-sm font-extrabold text-[#111827]">
                <Search size={16} /> Ürünleri İncele
              </Link>
            </div>
          </div>
          <aside className="grid gap-3 border-t border-[#E5E7EB] bg-[#FFF7ED] p-6 lg:border-l lg:border-t-0">
            <div className="rounded-lg border border-[#FED7AA] bg-white/80 p-4">
              <span className="block text-[11px] font-black uppercase tracking-[0.12em] text-[#6B7280]">Durum</span>
              <span className="mt-1 block text-sm font-bold text-[#111827]">{meta.text}</span>
            </div>
            <div className="rounded-lg border border-[#FED7AA] bg-white/80 p-4">
              <span className="block text-[11px] font-black uppercase tracking-[0.12em] text-[#6B7280]">Öneri</span>
              <span className="mt-1 block text-sm font-bold leading-6 text-[#111827]">{meta.recommendation}</span>
            </div>
            <div className="rounded-lg border border-[#FED7AA] bg-white/80 p-4">
              <span className="block text-[11px] font-black uppercase tracking-[0.12em] text-[#6B7280]">Hata UID</span>
              <span className="mt-1 block break-all font-mono text-xs font-bold text-[#111827]">{errorUid}</span>
            </div>
            <div className="rounded-lg border border-[#FED7AA] bg-white/80 p-4">
              <span className="block text-[11px] font-black uppercase tracking-[0.12em] text-[#6B7280]">İstek UID</span>
              <span className="mt-1 block break-all font-mono text-xs font-bold text-[#111827]">{requestUid}</span>
            </div>
          </aside>
        </section>
      </main>
    </GuestLayout>
  );
}
