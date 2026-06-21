import type { Metadata } from "next";
import Link from "next/link";
import Image from "next/image";
import { notFound } from "next/navigation";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { CouponCopyButton } from "@/app/_components/CouponCopyButton";
import { SeoHead } from "@/app/_components/SeoHead";
import {
  breadcrumbSchema,
  buildMetadata,
  jsonLdGraph,
  siteUrl,
  webPageSchema,
} from "@/lib/seo";
import { resolveInternalApiOrigin } from "@/lib/server-config";
import {
  Tag,
  Clock,
  ChevronRight,
  ArrowLeft,
  Zap,
  CheckCircle2,
  Users,
} from "lucide-react";

type NextFetchInit = RequestInit & { next?: { revalidate?: number; tags?: string[] } };

// ─── Types ────────────────────────────────────────────────────────────────────

type Coupon = {
  code: string;
  discount_type: "fixed" | "percent";
  discount_value: number;
  ends_at?: string | null;
};

type CampaignDetail = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  body?: string | null;
  banner_image_url?: string | null;
  meta_image_url?: string | null;
  badge_label?: string | null;
  color_hex: string;
  discount_label: string;
  discount_type: "fixed" | "percent";
  discount_value: number;
  starts_at?: string | null;
  ends_at?: string | null;
  coupons_count: number;
  coupons: Coupon[];
  seo?: { title?: string; description?: string } | null;
};

// ─── Data Fetching ────────────────────────────────────────────────────────────

function resolveBaseUrl() {
  return resolveInternalApiOrigin();
}

async function fetchCampaign(slug: string): Promise<CampaignDetail | null> {
  try {
    const res = await fetch(
      `${resolveBaseUrl()}/api/v1/content/campaigns/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: "application/json" },
        next: { revalidate: 300 },
      } as NextFetchInit,
    );
    if (res.status === 404) return null;
    if (!res.ok) return null;
    const json = await res.json();
    return json.data ?? null;
  } catch {
    return null;
  }
}

// ─── Metadata ─────────────────────────────────────────────────────────────────

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const campaign = await fetchCampaign(slug);

  if (!campaign) {
    return { title: "Kampanya Bulunamadı" };
  }

  const title = campaign.seo?.title ?? campaign.name;
  const description =
    campaign.seo?.description ??
    campaign.description ??
    `${campaign.name} — Karacabey Gross Market kampanyası.`;
  const image = campaign.meta_image_url ?? campaign.banner_image_url;

  return buildMetadata({
    title,
    description,
    path: `/kampanyalar/${campaign.slug}`,
    image: image ?? undefined,
    type: "article",
  });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(iso: string) {
  return new Intl.DateTimeFormat("tr-TR", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date(iso));
}

function formatCountdown(isoDate: string): string {
  const diff = new Date(isoDate).getTime() - Date.now();
  if (diff <= 0) return "Sona erdi";
  const days = Math.floor(diff / 86400000);
  const hours = Math.floor((diff % 86400000) / 3600000);
  if (days > 0) return `${days} gün ${hours} saat kaldı`;
  const minutes = Math.floor((diff % 3600000) / 60000);
  return `${hours} saat ${minutes} dk kaldı`;
}

function formatCouponDiscount(coupon: Coupon) {
  if (coupon.discount_type === "percent") return `%${coupon.discount_value}`;
  return `${(coupon.discount_value / 100).toFixed(2).replace(".", ",")} ₺`;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default async function CampaignDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const campaign = await fetchCampaign(slug);

  if (!campaign) notFound();

  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kampanyalar", label: "Kampanyalar" },
    { href: `/kampanyalar/${campaign.slug}`, label: campaign.name },
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: campaign.name,
      description: campaign.description ?? `${campaign.name} Karacabey Gross Market kampanyası.`,
      path: `/kampanyalar/${campaign.slug}`,
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    {
      "@type": "Offer",
      name: campaign.name,
      description: campaign.description,
      url: `${siteUrl}/kampanyalar/${campaign.slug}`,
      seller: {
        "@id": `${siteUrl}/#organization`,
      },
      ...(campaign.ends_at
        ? { priceValidUntil: campaign.ends_at.split("T")[0] }
        : {}),
    },
  ]);

  const hasImage = Boolean(campaign.banner_image_url);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="catalog-page">

        {/* Breadcrumb */}
        <nav className="flex items-center gap-2 text-xs text-[#6B7177] mb-4">
          <Link href="/" className="hover:text-[#FF7A00] transition-colors">Ana Sayfa</Link>
          <ChevronRight size={12} />
          <Link href="/kampanyalar" className="hover:text-[#FF7A00] transition-colors">Kampanyalar</Link>
          <ChevronRight size={12} />
          <span className="text-[#2B2F36] font-semibold truncate max-w-[180px]">{campaign.name}</span>
        </nav>

        {/* Hero Banner */}
        <header className="relative overflow-hidden rounded-3xl">
          {hasImage ? (
            <div className="relative h-64 sm:h-80 lg:h-96">
              <Image
                src={campaign.banner_image_url!}
                alt={campaign.name}
                fill
                priority
                className="object-cover"
                sizes="(max-width: 1440px) 100vw"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
              <div className="absolute bottom-0 left-0 right-0 p-6 sm:p-8">
                {campaign.badge_label && (
                  <span
                    className="mb-3 inline-flex rounded-full px-3 py-1 text-xs font-black text-white"
                    style={{ backgroundColor: campaign.color_hex }}
                  >
                    {campaign.badge_label}
                  </span>
                )}
                <h1 className="text-2xl font-black leading-tight text-white sm:text-3xl lg:text-4xl">
                  {campaign.name}
                </h1>
                {campaign.description && (
                  <p className="mt-2 text-sm leading-6 text-white/80">{campaign.description}</p>
                )}
              </div>
            </div>
          ) : (
            <div
              className="relative flex min-h-48 flex-col justify-end p-6 sm:p-8"
              style={{
                background: `linear-gradient(135deg, ${campaign.color_hex}22, ${campaign.color_hex}44)`,
                borderLeft: `4px solid ${campaign.color_hex}`,
              }}
            >
              <Zap size={32} className="absolute right-8 top-8 opacity-20" style={{ color: campaign.color_hex }} />
              {campaign.badge_label && (
                <span
                  className="mb-3 inline-flex w-fit rounded-full px-3 py-1 text-xs font-black text-white"
                  style={{ backgroundColor: campaign.color_hex }}
                >
                  {campaign.badge_label}
                </span>
              )}
              <h1 className="text-2xl font-black leading-tight text-[#111827] sm:text-3xl">
                {campaign.name}
              </h1>
              {campaign.description && (
                <p className="mt-2 text-sm leading-6 text-[#6B7177]">{campaign.description}</p>
              )}
            </div>
          )}
        </header>

        <div className="grid gap-6 lg:grid-cols-[1fr_340px] mt-6">
          {/* Sol — İçerik */}
          <div className="flex flex-col gap-6">

            {/* Meta bilgiler */}
            <div className="flex flex-wrap gap-3">
              <span
                className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-black text-white"
                style={{ backgroundColor: campaign.color_hex }}
              >
                <Tag size={14} />
                {campaign.discount_label}
              </span>
              {campaign.ends_at && (
                <span className="inline-flex items-center gap-1.5 rounded-full border border-[#FED7AA] bg-[#FFF7ED] px-3 py-1.5 text-sm font-semibold text-[#D97706]">
                  <Clock size={14} />
                  {formatCountdown(campaign.ends_at)}
                </span>
              )}
              {campaign.starts_at && (
                <span className="inline-flex items-center gap-1.5 rounded-full border border-[#E4E7EB] bg-[#F9FAFB] px-3 py-1.5 text-sm font-semibold text-[#6B7177]">
                  {formatDate(campaign.starts_at)}&apos;den itibaren
                </span>
              )}
            </div>

            {/* Body içerik */}
            {campaign.body && (
              <article
                className="prose prose-sm max-w-none rounded-2xl border border-[#E4E7EB] bg-white p-6"
                dangerouslySetInnerHTML={{ __html: campaign.body }}
              />
            )}

            {/* CTA — Ürünlere git */}
            <div className="rounded-2xl border border-[#FFE4C2] bg-[#FFF5EA] p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <p className="font-black text-[#2B2F36]">Bu kampanyadan yararlanmak için</p>
                <p className="text-sm text-[#6B7177] mt-0.5">Ürün sepetinize ekleyin, indirim otomatik uygulanır.</p>
              </div>
              <Link
                href="/products"
                className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-[#FF7A00] px-5 py-2.5 text-sm font-black text-white transition hover:bg-[#E06500]"
              >
                Alışverişe Başla <ChevronRight size={15} />
              </Link>
            </div>

            {/* Geri */}
            <Link
              href="/kampanyalar"
              className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-[#6B7177] hover:text-[#FF7A00] transition-colors"
            >
              <ArrowLeft size={14} /> Tüm Kampanyalara Dön
            </Link>
          </div>

          {/* Sağ — Kupon Kutusu */}
          <aside className="flex flex-col gap-4">
            <div className="rounded-2xl border border-[#E4E7EB] bg-white p-5 shadow-sm">
              <div className="flex items-center gap-2 mb-4">
                <Users size={16} className="text-[#FF7A00]" />
                <p className="text-sm font-black text-[#2B2F36]">
                  {campaign.coupons_count > 0
                    ? `${campaign.coupons_count} kupon kodu mevcut`
                    : "Kupon Bilgileri"}
                </p>
              </div>

              {campaign.coupons && campaign.coupons.length > 0 ? (
                <div className="flex flex-col gap-3">
                  {campaign.coupons.map((coupon) => (
                    <CouponBox key={coupon.code} coupon={coupon} />
                  ))}
                </div>
              ) : (
                <div className="rounded-xl bg-[#F9FAFB] px-4 py-5 text-center">
                  <CheckCircle2 size={24} className="mx-auto mb-2 text-[#FF7A00]" />
                  <p className="text-sm font-semibold text-[#2B2F36]">Koda gerek yok!</p>
                  <p className="text-xs text-[#6B7177] mt-1">İndirim sepette otomatik uygulanır.</p>
                </div>
              )}
            </div>

            {/* Tarihler */}
            {(campaign.starts_at ?? campaign.ends_at) && (
              <div className="rounded-2xl border border-[#E4E7EB] bg-white p-5 text-sm">
                <p className="font-black text-[#2B2F36] mb-3">Kampanya Tarihleri</p>
                {campaign.starts_at && (
                  <div className="flex justify-between py-1.5 border-b border-[#F3F4F6]">
                    <span className="text-[#6B7177]">Başlangıç</span>
                    <span className="font-semibold">{formatDate(campaign.starts_at)}</span>
                  </div>
                )}
                {campaign.ends_at && (
                  <div className="flex justify-between py-1.5">
                    <span className="text-[#6B7177]">Bitiş</span>
                    <span className="font-semibold text-[#D97706]">{formatDate(campaign.ends_at)}</span>
                  </div>
                )}
              </div>
            )}
          </aside>
        </div>
      </main>
    </GuestLayout>
  );
}

// ─── CouponBox — Kupon kopyalama (client'ta JS ile) ──────────────────────────

function CouponBox({ coupon }: { coupon: Coupon }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-dashed border-[#FFB574] bg-[#FFF5EA] px-4 py-3">
      <div>
        <span className="font-mono text-base font-black tracking-widest text-[#FF7A00]">
          {coupon.code}
        </span>
        <p className="text-xs text-[#6B7177] mt-0.5">
          {formatCouponDiscount(coupon)} indirim
          {coupon.ends_at && ` · ${formatDate(coupon.ends_at)}'e kadar`}
        </p>
      </div>
      <CouponCopyButton code={coupon.code} />
    </div>
  );
}
