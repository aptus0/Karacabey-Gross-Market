import { buildApiUrl } from "@/lib/api";
import { buildRequestSignal } from "@/lib/api";
import { resolveCachedResource } from "@/lib/client-cache";

export type NavigationItem = {
  id?: number;
  label: string;
  url: string;
  icon?: string | null;
  external?: boolean;
};

export type NavigationData = {
  top: NavigationItem[];
  header: NavigationItem[];
  category: NavigationItem[];
  footer_primary: NavigationItem[];
  footer_corporate: NavigationItem[];
  footer_support: NavigationItem[];
  footer_account: NavigationItem[];
};

export const defaultNavigation: NavigationData = {
  top: [
    { label: "Kargo Takip", url: "/kargo-takip", icon: "package-search" },
    { label: "Teslimat Bölgeleri", url: "/teslimat-bolgeleri", icon: "map-pin" },
    { label: "Destek & İletişim", url: "/iletisim", icon: "phone" },
  ],
  header: [
    { label: "Ürünler", url: "/products", icon: "grid" },
    { label: "Kampanyalar", url: "/kampanyalar", icon: "tag" },
    { label: "Adreslerim", url: "/account/addresses", icon: "map-pin" },
  ],
  category: [
    { label: "Süt ve Kahvaltılık", url: "/kategori/sut-ve-kahvaltilik", icon: "grid" },
    { label: "Fırın", url: "/kategori/firin", icon: "grid" },
    { label: "Meyve Sebze", url: "/kategori/meyve-sebze", icon: "grid" },
    { label: "Temel Gıda", url: "/kategori/temel-gida", icon: "grid" },
    { label: "Tüm Ürünler", url: "/products", icon: "grid" },
  ],
  footer_primary: [
    { label: "Ürünler", url: "/products", icon: "grid" },
    { label: "Kampanyalar", url: "/kampanyalar", icon: "tag" },
    { label: "Sepet", url: "/checkout", icon: "cart" },
  ],
  footer_corporate: [
    { label: "Hakkımızda", url: "/hakkimizda", icon: "file-text" },
    { label: "İletişim", url: "/iletisim", icon: "phone" },
    { label: "KVKK", url: "/kvkk", icon: "shield" },
  ],
  footer_support: [
    { label: "İade ve İptal", url: "/iade-ve-iptal-kosullari", icon: "package-search" },
    { label: "SSS", url: "/sikca-sorulan-sorular", icon: "file-text" },
  ],
  footer_account: [
    { label: "Hesabım", url: "/account", icon: "user" },
    { label: "Favoriler", url: "/favorites", icon: "heart" },
  ],
};

type NavigationResponse = {
  data?: Partial<NavigationData>;
};

export async function fetchNavigation(signal?: AbortSignal): Promise<NavigationData> {
  const payload = await resolveCachedResource<NavigationResponse>({
    cacheKey: "navigation:v1",
    maxAgeMs: 1000 * 60 * 60 * 12,
    fallback: { data: defaultNavigation },
    fetcher: async () => {
      const response = await fetch(buildApiUrl("/api/v1/content/navigation"), {
        headers: {
          Accept: "application/json",
        },
        signal: buildRequestSignal(signal, 6_000),
      });

      if (!response.ok) {
        throw new Error("Navigation could not be loaded.");
      }

      return (await response.json()) as NavigationResponse;
    },
  });

  return {
    ...defaultNavigation,
    ...payload.data,
  };
}
