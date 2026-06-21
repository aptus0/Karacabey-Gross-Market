import { getMarketingConfig } from "@/lib/marketing";
import { ConsentAwareMarketingPixels } from "@/app/_components/ConsentAwareMarketingPixels";

/**
 * Tüm pazarlama/tracking script'lerini admin panelinden gelen config'e göre enjekte eder.
 *
 * Desteklenen kanallar:
 * - Google: GTM, GA4 (gtag), Ads (gtag) — tek satır gtag init
 * - Meta: Pixel (fbq)
 * - Yandex: Metrica (ym)
 * - Microsoft: UET (uetq), Clarity
 * - TikTok: Pixel (ttq)
 *
 * `tracking_enabled=false` ise hiçbir script çıkmaz (KVKK opt-out).
 */
export async function MarketingPixels() {
  const config = await getMarketingConfig();

  if (!config.tracking_enabled) return null;

  return <ConsentAwareMarketingPixels config={config} />;
}
