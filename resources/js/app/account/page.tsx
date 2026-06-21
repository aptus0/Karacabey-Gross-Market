import type { Metadata } from "next";
import { buildMetadata } from "@/lib/seo";
import { AccountExperience } from "@/app/_components/AccountExperience";

export const metadata: Metadata = buildMetadata({
  title: "Hesabım",
  description: "Karacabey Gross Market hesap paneli — siparişlerinizi, adreslerinizi ve hesap bilgilerinizi yönetin.",
  path: "/account",
  keywords: ["hesabım", "siparişlerim", "adreslerim", "hesap yönetimi"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function AccountPage() {
  return <AccountExperience />;
}
