import { buildApiUrl, buildRequestSignal } from "@/lib/api";
import { resolveCachedResource } from "@/lib/client-cache";

export type CategoryMenuItem = {
  id?: number;
  slug: string;
  name: string;
  description?: string | null;
  imageUrl?: string | null;
  count?: number;
  children: Array<{
    id?: number;
    slug: string;
    name: string;
    description?: string | null;
  }>;
};

type CategoryMenuResponse = {
  data?: Array<{
    id: number;
    slug: string;
    name: string;
    description?: string | null;
    image_url?: string | null;
    product_count?: number;
    children?: Array<{
      id: number;
      slug: string;
      name: string;
      description?: string | null;
    }>;
  }>;
};

export const defaultCategoryMenu: CategoryMenuItem[] = [
  {
    slug: "temel-gida",
    name: "Temel Gıda",
    description: "Bakliyat, pirinç, bulgur, makarna, un, yağ, şeker ve tuz.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "meyve-sebze",
    name: "Meyve & Sebze",
    description: "Taze meyveler, sebzeler, yeşillikler ve organik ürünler.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "sarkuteri",
    name: "Şarküteri",
    description: "Peynir, zeytin, sucuk, salam, sosis ve pastırma seçkileri.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "et-tavuk-balik",
    name: "Et, Tavuk & Balık",
    description: "Kırmızı et, tavuk, balık ve dondurulmuş protein ürünleri.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "sut-kahvaltilik",
    name: "Süt & Kahvaltılık",
    description: "Süt, yoğurt, ayran, yumurta, reçel ve bal ürünleri.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "atistirmalik-icecek",
    name: "Atıştırmalık & İçecek",
    description: "Cips, çikolata, bisküvi, gazlı içecek ve meyve suyu.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "temizlik-urunleri",
    name: "Temizlik Ürünleri",
    description: "Deterjan, bulaşık ürünleri ve yüzey temizleyiciler.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "kozmetik-bakim",
    name: "Kozmetik & Bakım",
    description: "Makyaj, bakım, kişisel bakım ve seyahat ürünleri.",
    imageUrl: null,
    children: [
      { slug: "makyaj-urunleri", name: "Makyaj Ürünleri", description: "Ruj, fondöten, maskara ve far paleti." },
      { slug: "cilt-sac-bakimi", name: "Cilt & Saç Bakımı", description: "Yüz kremi, serum, şampuan ve saç bakımı." },
      { slug: "kisisel-bakim", name: "Kişisel Bakım", description: "Deodorant, duş jeli ve ağız bakım ürünleri." },
      { slug: "canta-aksesuar", name: "Çanta & Aksesuar", description: "Makyaj çantası, organizer ve aksesuarlar." },
      { slug: "bavul-seyahat", name: "Bavul & Seyahat", description: "Bavul, valiz ve seyahat düzenleyiciler." },
    ],
  },
  {
    slug: "zuccaciye-mutfak",
    name: "Züccaciye & Mutfak",
    description: "Tabak, bardak, tencere, tava ve saklama kapları.",
    imageUrl: null,
    children: [],
  },
  {
    slug: "hirdavat-ev-gerecleri",
    name: "Hırdavat & Ev Gereçleri",
    description: "Ampul, pil, bant, priz ve günlük küçük ev ihtiyaçları.",
    imageUrl: null,
    children: [],
  },
];

export async function fetchCategoryMenu(signal?: AbortSignal): Promise<CategoryMenuItem[]> {
  const payload = await resolveCachedResource<CategoryMenuResponse>({
    cacheKey: "categories:v1",
    maxAgeMs: 1000 * 60 * 60 * 12,
    fallback: { data: defaultCategoryMenu.map((item) => ({
      slug: item.slug,
      name: item.name,
      description: item.description ?? null,
      image_url: item.imageUrl ?? null,
      children: item.children.map((child) => ({
        slug: child.slug,
        name: child.name,
        description: child.description ?? null,
      })),
    })) as CategoryMenuResponse["data"] },
    fetcher: async () => {
      const response = await fetch(buildApiUrl("/api/v1/categories"), {
        headers: {
          Accept: "application/json",
        },
        signal: buildRequestSignal(signal, 6_000),
      });

      if (!response.ok) {
        throw new Error("Category menu could not be loaded.");
      }

      return (await response.json()) as CategoryMenuResponse;
    },
  });

  const items = (payload.data ?? []).map((item) => ({
    id: item.id,
    slug: item.slug,
    name: item.name,
    description: item.description ?? null,
    imageUrl: item.image_url ?? null,
    count: typeof item.product_count === "number" ? item.product_count : undefined,
    children: (item.children ?? []).map((child) => ({
      id: child.id,
      slug: child.slug,
      name: child.name,
      description: child.description ?? null,
    })),
  }));

  return items.length > 0 ? items : defaultCategoryMenu;
}
