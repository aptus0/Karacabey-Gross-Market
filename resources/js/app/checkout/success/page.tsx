import type { Metadata } from "next";
import { Suspense } from "react";
import { CheckoutConversionTracker } from "@/app/_components/CheckoutConversionTracker";
import { CheckoutResultPanel } from "@/app/_components/CheckoutResultPanel";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { getMarketingConfig } from "@/lib/marketing";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Ödeme Başarılı",
  description: "Karacabey Gross Market ödeme başarı sonucu.",
  path: "/checkout/success",
  robots: { index: false, follow: false },
});

export default async function CheckoutSuccessPage() {
  const marketing = await getMarketingConfig();

  return (
    <AppLayout>
      <Suspense fallback={null}>
        <CheckoutConversionTracker
          googleAdsId={marketing.google?.ads_id}
          googleAdsConversionLabel={marketing.google?.ads_conversion_label}
        />
        <CheckoutResultPanel result="success" />
      </Suspense>
    </AppLayout>
  );
}
