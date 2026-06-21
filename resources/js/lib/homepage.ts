import { buildApiUrl, buildRequestSignal } from "@/lib/api";

export type HomepageBlock = {
  id: number;
  type: string;
  title?: string | null;
  subtitle?: string | null;
  image_url?: string | null;
  link_url?: string | null;
  link_label?: string | null;
};

type HomepageResponse = {
  data?: {
    blocks?: HomepageBlock[];
  };
};

export const fallbackSlides: HomepageBlock[] = [
  {
    id: 1,
    type: "carousel_slide",
    title: "Karacabey Gross Market",
    subtitle: "Online market alışverişinde taze ürün, temel gıda ve hızlı teslimat.",
    image_url: "https://cdn.karacabeygrossmarket.com/campaigns/hosgeldin-indirimi.webp",
    link_url: "/products",
    link_label: "Alışverişe Başla",
  },
  {
    id: 2,
    type: "carousel_slide",
    title: "Günün Market Fırsatları",
    subtitle: "Sepetinizi temel ihtiyaç, temizlik, kahvaltılık ve taze ürünlerle tamamlayın.",
    image_url: "https://cdn.karacabeygrossmarket.com/campaigns/firsat-kampanyasi.webp",
    link_url: "/kampanyalar",
    link_label: "Kampanyaları Gör",
  },
];

export async function fetchHomepageBlocks(signal?: AbortSignal): Promise<HomepageBlock[]> {
  try {
    const response = await fetch(buildApiUrl("/api/v1/content/homepage?channel=web"), {
        headers: {
          Accept: "application/json",
        },
        signal: buildRequestSignal(signal, 6_000),
        cache: "no-store",
      });

    if (!response.ok) {
      throw new Error("Homepage content failed.");
    }

    const payload = (await response.json()) as HomepageResponse;

    return payload.data?.blocks ?? fallbackSlides;
  } catch {
    return fallbackSlides;
  }
}
