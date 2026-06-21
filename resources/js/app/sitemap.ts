import type { MetadataRoute } from "next";
import { blogPosts } from "@/lib/blog";
import { storeCampaigns, storePages } from "@/lib/content";
import type { KgmProduct } from "@/lib/catalog";
import { publicPages } from "@/lib/public-pages";
import {
  absoluteImageUrl,
  absoluteUrl,
  defaultOpenGraphImage,
  productSeoImages,
  siteUrl,
} from "@/lib/seo";
import { resolveInternalApiOrigin } from "@/lib/server-config";
import {
  fetchStorefrontCategories,
  fetchStorefrontProducts,
} from "@/lib/storefront-products";

type NextFetchInit = RequestInit & { next?: { revalidate?: number; tags?: string[] } };

type SitemapEntry = MetadataRoute.Sitemap[number];
type ChangeFrequency = NonNullable<SitemapEntry["changeFrequency"]>;

type CampaignSitemapItem = {
  slug: string;
  updatedAt?: string | null;
  image?: string | null;
};

const baseUrl = siteUrl;
const staticLastModified = new Date("2026-05-19T00:00:00.000Z");
const maxProductPages = 25;
const productsPerPage = 200;

function route(path = "/") {
  return absoluteUrl(path);
}

function alternate(path = "/") {
  return {
    languages: {
      "tr-TR": route(path),
      "x-default": route(path),
    },
  };
}

function entry({
  path,
  lastModified = staticLastModified,
  changeFrequency,
  priority,
  images,
}: {
  path?: string;
  lastModified?: Date | string;
  changeFrequency: ChangeFrequency;
  priority: number;
  images?: string[];
}): SitemapEntry {
  const normalizedPath = path ?? "/";

  return {
    url: route(normalizedPath),
    lastModified,
    changeFrequency,
    priority,
    alternates: alternate(normalizedPath),
    images: images?.map(absoluteImageUrl),
  };
}

function uniqueEntries(entries: SitemapEntry[]) {
  const seen = new Set<string>();

  return entries.filter((item) => {
    if (seen.has(item.url)) {
      return false;
    }

    seen.add(item.url);
    return true;
  });
}

async function fetchAllSitemapProducts() {
  const allProducts: KgmProduct[] = [];

  for (let page = 1; page <= maxProductPages; page += 1) {
    const result = await fetchStorefrontProducts({
      page,
      perPage: productsPerPage,
    });

    allProducts.push(...result.products);

    if (page >= result.lastPage || result.products.length === 0) {
      break;
    }
  }

  return allProducts;
}

async function fetchCampaignSitemapItems(): Promise<CampaignSitemapItem[]> {
  try {
    const response = await fetch(`${resolveInternalApiOrigin()}/api/v1/content/campaigns`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 300 },
    } as NextFetchInit);

    if (!response.ok) {
      throw new Error(`Campaign sitemap request failed with ${response.status}.`);
    }

    const payload = (await response.json()) as {
      data?: Array<{
        slug?: string;
        updated_at?: string | null;
        banner_image_url?: string | null;
        meta_image_url?: string | null;
      }>;
    };

    const apiCampaigns = (payload.data ?? [])
      .filter((campaign): campaign is Required<Pick<typeof campaign, "slug">> & typeof campaign =>
        typeof campaign.slug === "string" && campaign.slug.trim().length > 0,
      )
      .map((campaign) => ({
        slug: campaign.slug,
        updatedAt: campaign.updated_at ?? null,
        image: campaign.meta_image_url ?? campaign.banner_image_url ?? null,
      }));

    return apiCampaigns.length > 0 ? apiCampaigns : fallbackCampaignSitemapItems();
  } catch {
    return fallbackCampaignSitemapItems();
  }
}

function fallbackCampaignSitemapItems(): CampaignSitemapItem[] {
  return storeCampaigns.map((campaign) => ({
    slug: campaign.slug,
    updatedAt: null,
    image: null,
  }));
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [products, categories, campaigns] = await Promise.all([
    fetchAllSitemapProducts(),
    fetchStorefrontCategories(),
    fetchCampaignSitemapItems(),
  ]);
  const publicPageSlugs = new Set(publicPages.map((page) => page.slug));
  const categoryRoutes = categories.flatMap((category) => [
    category,
    ...(category.children ?? []),
  ]);

  const entries: SitemapEntry[] = [
    entry({
      path: "/",
      changeFrequency: "daily",
      priority: 1,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/products",
      changeFrequency: "daily",
      priority: 0.92,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/kategoriler",
      changeFrequency: "weekly",
      priority: 0.84,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/kampanyalar",
      changeFrequency: "daily",
      priority: 0.86,
      images: [defaultOpenGraphImage],
    }),
    ...categoryRoutes.map((category) => entry({
      path: `/kategori/${category.slug}`,
      changeFrequency: "daily",
      priority: typeof category.count === "number" && category.count > 0 ? 0.78 : 0.68,
      images: category.imageUrl ? [category.imageUrl] : [defaultOpenGraphImage],
    })),
    ...products.map((product) => {
      const productImages = productSeoImages(product);

      return entry({
        path: `/product/${product.slug}`,
        lastModified: product.updatedAt ? new Date(product.updatedAt) : staticLastModified,
        changeFrequency: product.stock > 0 ? "daily" : "weekly",
        priority: product.stock > 0 ? 0.82 : 0.58,
        images: productImages.length > 0 ? productImages : undefined,
      });
    }),
    ...campaigns.map((campaign) => entry({
      path: `/kampanyalar/${campaign.slug}`,
      lastModified: campaign.updatedAt ? new Date(campaign.updatedAt) : staticLastModified,
      changeFrequency: "daily",
      priority: 0.78,
      images: campaign.image ? [campaign.image] : [defaultOpenGraphImage],
    })),
    ...storePages
      .filter((page) => !publicPageSlugs.has(page.slug))
      .map((page) => entry({
        path: `/kurumsal/${page.slug}`,
        changeFrequency: "monthly",
        priority: page.group === "corporate" ? 0.62 : 0.5,
        images: [defaultOpenGraphImage],
      })),
    entry({
      path: "/kargo-hesaplama",
      changeFrequency: "weekly",
      priority: 0.76,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/kurumsal/kargo-entegrasyonlari",
      changeFrequency: "monthly",
      priority: 0.62,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/kargo-takip",
      changeFrequency: "weekly",
      priority: 0.74,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/mobile",
      changeFrequency: "monthly",
      priority: 0.86,
      images: [defaultOpenGraphImage],
    }),
    entry({
      path: "/blog",
      changeFrequency: "weekly",
      priority: 0.74,
      images: [defaultOpenGraphImage],
    }),
    ...blogPosts.map((post) => entry({
      path: `/blog/${post.slug}`,
      lastModified: new Date(post.publishedAt),
      changeFrequency: "monthly",
      priority: post.seo.schemaType === "HowTo" || post.seo.schemaType === "FAQPage" ? 0.68 : 0.64,
      images: [post.heroImage],
    })),
    ...publicPages.map((page) => entry({
      path: `/${page.slug}`,
      changeFrequency: page.slug.includes("kargo") || page.slug.includes("teslimat") ? "weekly" : "monthly",
      priority: page.slug.includes("kargo") || page.slug.includes("teslimat") ? 0.72 : 0.58,
      images: [defaultOpenGraphImage],
    })),
  ];

  return uniqueEntries(entries)
    .sort((first, second) => second.priority! - first.priority! || first.url.localeCompare(second.url));
}
