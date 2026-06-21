import type { Metadata } from "next";
import { CheckoutExperience } from "@/app/_components/CheckoutExperience";
import { CheckoutLayout } from "@/app/_layouts/CheckoutLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Güvenli Ödeme - Karacabey Gross Market",
  description: "Karacabey Gross Market güvenli checkout ve ödeme adımı.",
  path: "/checkout",
  keywords: ["checkout", "ödeme", "satın al"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function CheckoutPage() {
  return (
    <CheckoutLayout>
      <CheckoutExperience />
    </CheckoutLayout>
  );
}
