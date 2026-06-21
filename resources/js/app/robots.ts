import type { MetadataRoute } from "next";
import { siteUrl } from "@/lib/seo";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: [
          "/",
          "/products",
          "/product/",
          "/kategori/",
          "/kategoriler",
          "/kampanyalar",
          "/mobile",
          "/google-merchant.xml",
          "/indexnow/",
          "/kargo-hesaplama",
          "/opensearch.xml",
          "/kargo-takip",
          "/blog",
          "/hakkimizda",
          "/iletisim",
          "/yardim",
          "/sikca-sorulan-sorular",
        ],
        disallow: [
          "/account",
          "/addresses",
          "/auth",
          "/checkout",
          "/favorites",
          "/hesabim",
          "/notifications",
          "/sepet",
          "/*?q=",
          "/*?page=",
          "/*?category=",
          "/*&q=",
          "/*&page=",
          "/*&category=",
        ],
      },
      {
        userAgent: "Googlebot",
        allow: "/",
        disallow: [
          "/account",
          "/addresses",
          "/auth",
          "/checkout",
          "/favorites",
          "/hesabim",
          "/notifications",
          "/sepet",
        ],
      },
      {
        userAgent: "Googlebot-Image",
        allow: ["/", "/product/", "/kategori/", "/assets/", "/storage/"],
        disallow: ["/account", "/auth", "/checkout"],
      },
      {
        userAgent: "AdsBot-Google",
        allow: ["/", "/product/", "/products", "/kategori/", "/kategoriler", "/kampanyalar", "/google-merchant.xml"],
        disallow: ["/account", "/auth", "/checkout", "/sepet", "/hesabim"],
      },
      {
        userAgent: "AdsBot-Google-Mobile",
        allow: ["/", "/product/", "/products", "/kategori/", "/kategoriler", "/kampanyalar", "/google-merchant.xml"],
        disallow: ["/account", "/auth", "/checkout", "/sepet", "/hesabim"],
      },
    ],
    sitemap: [
      `${siteUrl}/sitemap.xml`,
      `${siteUrl}/seo/product-sitemap.xml`,
      `${siteUrl}/seo/product-images-sitemap.xml`,
    ],
    host: siteUrl,
  };
}
