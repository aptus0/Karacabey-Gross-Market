import type { Metadata } from "next";
import { Suspense } from "react";
import { CheckoutResultPanel } from "@/app/_components/CheckoutResultPanel";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Ödeme Tamamlanamadı",
  description: "Karacabey Gross Market ödeme sonucu ve tekrar deneme ekranı.",
  path: "/checkout/fail",
  robots: { index: false, follow: false },
});

export default function CheckoutFailPage() {
  return (
    <AppLayout>
      <Suspense fallback={null}>
        <CheckoutResultPanel result="fail" />
      </Suspense>
    </AppLayout>
  );
}
