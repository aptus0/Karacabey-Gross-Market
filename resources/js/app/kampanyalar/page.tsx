import type { Metadata } from "next";
import Link from "next/link";
import Image from "next/image";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { SeoHead } from "@/app/_components/SeoHead";
import {
  breadcrumbSchema,
  buildMetadata,
  itemListSchema,
  jsonLdGraph,
  siteUrl,
  webPageSchema,
} from "@/lib/seo";
import { resolveInternalApiOrigin } from "@/lib/server-config";
import { Tag, Clock, ChevronRight, Zap } from "lucide-react";

type NextFetchInit = RequestInit & { next?: { revalidate?: number; tags?: string[] } };

export const metadata: Metadata = buildMetadata({
  title: "Kampanyalar & Fırsatlar",
  description:
    "Karacabey Gross Market güncel kampanyaları, indirim fırsatları ve kupon kodları. En iyi market tekliflerini kaçırma.",
  path: "/kampanyalar",
  keywords: [
    "kampanyalar",
    "indirim",
    "fırsat",
    "kupon kodu",
    "market indirimi",
    "Karacabey market kampanya",
    "gross market indirim",
  ],
});

type Campaign = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  banner_image_url?: string | null;
  badge_label?: string | null;
  color_hex: string;
  discount_label: string;
  ends_at?: string | null;
  coupons_count: number;
  seo?: { title?: string; description?: string } | null;
};

async function fetchCampaigns(): Promise<Campaign[]> {
  const baseUrl = resolveInternalApiOrigin();

  try {
    const res = await fetch(`${baseUrl}/api/v1/content/campaigns?channel=web`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 300 }, // 5 dakika cache
    } as NextFetchInit);
    if (!res.ok) return fallbackCampaigns;
    const json = await res.json();
    return Array.isArray(json.data) ? json.data : fallbackCampaigns;
  } catch {
    return fallbackCampaigns;
  }
}

// API hazır değilken gösterilecek demo kampanyalar
const fallbackCampaigns: Campaign[] = [
  {
    id: 1,
    name: "Yaz Fırsatları %20 İndirim",
    slug: "yaz-firsatlari",
    description: "Seçili ürünlerde %20'ye varan indirim fırsatını kaçırmayın.",
    banner_image_url: null,
    badge_label: "Sınırlı Süre",
    color_hex: "#FF7A00",
    discount_label: "%20 İndirim",
    ends_at: new Date(Date.now() + 7 * 86400000).toISOString(),
    coupons_count: 2,
    seo: null,
  },
  {
    id: 2,
    name: "Toplu Alıma Özel 50₺ İndirim",
    slug: "toplu-alim-indirimi",
    description: "500₺ ve üzeri siparişlerinizde 50₺ indirim kazanın.",
    banner_image_url: null,
    badge_label: "Kurumsal",
    color_hex: "#1A2744",
    discount_label: "50₺ İndirim",
    ends_at: null,
    coupons_count: 1,
    seo: null,
  },
];

function formatCountdown(isoDate: string): string {
  const diff = new Date(isoDate).getTime() - Date.now();
  if (diff <= 0) return "Sona erdi";
  const days = Math.floor(diff / 86400000);
  const hours = Math.floor((diff % 86400000) / 3600000);
  if (days > 0) return `${days} gün ${hours} saat kaldı`;
  const minutes = Math.floor((diff % 3600000) / 60000);
  return `${hours} saat ${minutes} dk kaldı`;
}

export default async function CampaignsPage() {
  const campaigns = await fetchCampaigns();

  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kampanyalar", label: "Kampanyalar" },
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: "Kampanyalar & Fırsatlar",
      description: "Karacabey Gross Market güncel kampanyaları, indirim fırsatları ve kupon kodları.",
      path: "/kampanyalar",
      type: "CollectionPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    itemListSchema({
      name: "Karacabey Gross Market Kampanyalar",
      description: "Güncel kampanyalar ve indirim fırsatları.",
      path: "/kampanyalar",
      items: campaigns.map((campaign) => ({
        name: campaign.name,
        url: `/kampanyalar/${campaign.slug}`,
      })),
    }),
    ...campaigns.map((c) => ({
      "@type": "Offer",
      name: c.name,
      description: c.description,
      url: `${siteUrl}/kampanyalar/${c.slug}`,
    })),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="catalog-page">

        {/* Hero */}
        <section className="catalog-hero">
          <div>
            <p className="eyebrow">Kampanyalar</p>
            <h1>Fırsatlar & İndirimler</h1>
            <p className="mt-1 text-sm text-[#6B7177]">
              {campaigns.length} aktif kampanya · Güncel teklifler
            </p>
          </div>
          <Link className="secondary-action" href="/products">
            Tüm Ürünler
          </Link>
        </section>

        {/* Kampanya kartları */}
        {campaigns.length === 0 ? (
          <div className="flex flex-col items-center gap-4 py-20 text-center">
            <Tag size={40} className="text-[#D1D5DB]" />
            <p className="text-[#6B7177]">Şu an aktif kampanya bulunmuyor.</p>
          </div>
        ) : (
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {campaigns.map((campaign) => (
              <CampaignCard key={campaign.id} campaign={campaign} />
            ))}
          </div>
        )}
      </main>
    </GuestLayout>
  );
}

// ─── Kampanya Kartı ───────────────────────────────────────────────────────────

function CampaignCard({ campaign }: { campaign: Campaign }) {
  const hasImage = Boolean(campaign.banner_image_url);

  return (
    <Link
      href={`/kampanyalar/${campaign.slug}`}
      className="group relative flex flex-col overflow-hidden rounded-3xl border border-[#E4E7EB] bg-white shadow-sm transition hover:shadow-lg hover:-translate-y-0.5"
    >
      {/* Görsel ya da renkli banner */}
      <div className="relative h-44 overflow-hidden">
        {hasImage ? (
          <Image
            src={campaign.banner_image_url!}
            alt={campaign.name}
            fill
            className="object-cover transition group-hover:scale-105"
            sizes="(max-width: 640px) 100vw, 50vw"
          />
        ) : (
          <div
            className="flex h-full w-full items-center justify-center"
            style={{ background: `linear-gradient(135deg, ${campaign.color_hex}dd, ${campaign.color_hex})` }}
          >
            <Zap size={48} className="text-white/40" />
          </div>
        )}

        {/* İndirim rozeti */}
        <div
          className="absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-black text-white shadow"
          style={{ backgroundColor: campaign.color_hex }}
        >
          {campaign.discount_label}
        </div>

        {/* Badge label */}
        {campaign.badge_label && (
          <div className="absolute right-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-black text-[#111827] backdrop-blur-sm shadow">
            {campaign.badge_label}
          </div>
        )}
      </div>

      {/* İçerik */}
      <div className="flex flex-1 flex-col gap-3 p-5">
        <h2 className="text-base font-black leading-tight text-[#111827] line-clamp-2 group-hover:text-[#FF7A00] transition-colors">
          {campaign.name}
        </h2>

        {campaign.description && (
          <p className="text-sm leading-6 text-[#6B7177] line-clamp-2">
            {campaign.description}
          </p>
        )}

        <div className="mt-auto flex items-center justify-between gap-3 pt-2">
          {/* Countdown */}
          {campaign.ends_at ? (
            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#D97706]">
              <Clock size={12} />
              {formatCountdown(campaign.ends_at)}
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#16A34A]">
              <Tag size={12} />
              Süresiz kampanya
            </span>
          )}

          {/* Kupon sayısı */}
          {campaign.coupons_count > 0 && (
            <span className="rounded-full bg-[#FFF3E6] px-2 py-0.5 text-xs font-black text-[#FF7A00]">
              {campaign.coupons_count} kupon
            </span>
          )}
        </div>

        <div className="flex items-center gap-1 text-xs font-black uppercase tracking-wider text-[#FF7A00]">
          Kampanyayı İncele <ChevronRight size={13} />
        </div>
      </div>
    </Link>
  );
}
