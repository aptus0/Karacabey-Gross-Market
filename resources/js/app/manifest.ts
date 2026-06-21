import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Karacabey Gross Market",
    short_name: "KGM",
    description: "Karacabey Gross Market online market, hızlı teslimat ve güvenli ödeme deneyimi.",
    start_url: "/",
    scope: "/",
    display: "standalone",
    background_color: "#eef1f4",
    theme_color: "#111827",
    lang: "tr-TR",
    categories: ["shopping", "food", "business"],
    icons: [
      {
        src: "/assets/kgm-favicon-256.png",
        sizes: "256x256",
        type: "image/png",
        purpose: "maskable",
      },
      {
        src: "/assets/kgm-logo.png",
        sizes: "1400x742",
        type: "image/png",
      },
    ],
  };
}
