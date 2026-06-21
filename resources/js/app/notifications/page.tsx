import type { Metadata } from "next";
import { NotificationsExperience } from "@/app/_components/NotificationsExperience";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Bildirimler",
  description: "Yeni kampanyalar, ürün güncellemeleri ve sipariş haberleri için bildirim merkezi.",
  path: "/notifications",
  keywords: ["bildirim", "kampanya bildirimi", "ürün bildirimi", "hesap bildirimi"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function NotificationsPage() {
  return <NotificationsExperience />;
}
