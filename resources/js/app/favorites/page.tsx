import type { Metadata } from "next";
import { FavoritesExperience } from "@/app/_components/FavoritesExperience";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Favoriler",
  description: "Karacabey Gross Market favori ürünler listeniz.",
  path: "/favorites",
  keywords: ["favoriler", "kayıtlı ürünler", "tekrar sipariş"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function FavoritesPage() {
  return <FavoritesExperience />;
}
