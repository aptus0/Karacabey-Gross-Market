import type { Metadata } from "next";
import { CustomerSupportHub } from "@/app/_components/CustomerSupportHub";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Destek",
  description: "Karacabey Gross Market destek merkezi.",
  path: "/account/support",
  robots: { index: false, follow: false },
});

export default function CustomerSupportPage() {
  return (
    <AppLayout>
      <CustomerSupportHub />
    </AppLayout>
  );
}
