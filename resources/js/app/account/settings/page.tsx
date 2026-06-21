import type { Metadata } from "next";
import { buildMetadata } from "@/lib/seo";
import { AccountSettings } from "@/app/_components/AccountSettings";

export const metadata: Metadata = buildMetadata({
  title: "Ayarlar",
  description: "Hesap ayarlarını ve kişisel bilgilerinizi yönetin",
  path: "/account/settings",
  robots: {
    index: false,
    follow: false,
  },
});

export default function SettingsPage() {
  return <AccountSettings />;
}
