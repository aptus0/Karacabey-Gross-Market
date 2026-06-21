import "server-only";
import { resolveInternalApiOrigin } from "@/lib/server-config";

type NextFetchInit = RequestInit & { next?: { revalidate?: number; tags?: string[] } };

export type MarketingConfig = {
  tracking_enabled: boolean;
  announcement_text?: string | null;
  google?: {
    analytics_id?: string;
    ads_id?: string;
    ads_conversion_label?: string;
    site_verification?: string;
    gtm_id?: string;
    merchant_id?: string;
    maps_api_key?: string;
    admob_app_id?: string;
    admob_ios_banner_unit_id?: string;
    admob_ios_interstitial_unit_id?: string;
    admob_android_banner_unit_id?: string;
    admob_android_interstitial_unit_id?: string;
  };
  meta?: {
    pixel_id?: string;
    catalog_id?: string;
    business_id?: string;
  };
  yandex?: {
    metrica_id?: string;
    verification?: string;
    direct_counter_id?: string;
  };
  microsoft?: {
    uet_tag_id?: string;
    clarity_id?: string;
    bing_verification?: string;
  };
  tiktok?: {
    pixel_id?: string;
  };
};

const FALLBACK: MarketingConfig = { tracking_enabled: false };

/**
 * Pazarlama config'ini admin panelinden çeker.
 * 5 dakika cache; API çağrısı başarısız olursa env fallback'i devreye girer.
 */
export async function getMarketingConfig(): Promise<MarketingConfig> {
  // Build-time'da API erişilemiyor olabilir → env fallback
  if (process.env.NEXT_PHASE === "phase-production-build") {
    return envFallback();
  }

  try {
    const origin = resolveInternalApiOrigin();
    if (!origin) return envFallback();

    const response = await fetch(`${origin}/api/v1/content/marketing`, {
      next: { revalidate: 300, tags: ["marketing-config"] },
      headers: { Accept: "application/json" },
    } as NextFetchInit);

    if (!response.ok) return envFallback();

    const body = (await response.json()) as { data?: MarketingConfig };
    const data = body.data ?? FALLBACK;

    // Eğer kullanıcı admin'de bir şey ayarlamadıysa env fallback'i de uygula
    if (!data.google?.analytics_id && process.env.GOOGLE_ANALYTICS_ID) {
      data.google = { ...data.google, analytics_id: process.env.GOOGLE_ANALYTICS_ID };
    }
    if (!data.meta?.pixel_id && process.env.META_PIXEL_ID) {
      data.meta = { ...data.meta, pixel_id: process.env.META_PIXEL_ID };
    }

    return data;
  } catch {
    return envFallback();
  }
}

function envFallback(): MarketingConfig {
  const trackingEnabled = process.env.NEXT_PUBLIC_ENABLE_MARKETING_PIXELS === "true";
  if (!trackingEnabled) return FALLBACK;

  return {
    tracking_enabled: true,
    google: {
      analytics_id: process.env.GOOGLE_ANALYTICS_ID,
      ads_id: process.env.GOOGLE_ADS_ID,
      ads_conversion_label: process.env.GOOGLE_ADS_CONVERSION_LABEL,
      site_verification: process.env.GOOGLE_SITE_VERIFICATION,
      gtm_id: process.env.GOOGLE_GTM_ID,
      merchant_id: process.env.GOOGLE_MERCHANT_ID,
      maps_api_key: process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY,
      admob_app_id: process.env.GOOGLE_ADMOB_APP_ID,
      admob_ios_banner_unit_id: process.env.GOOGLE_ADMOB_IOS_BANNER_UNIT_ID,
      admob_ios_interstitial_unit_id: process.env.GOOGLE_ADMOB_IOS_INTERSTITIAL_UNIT_ID,
      admob_android_banner_unit_id: process.env.GOOGLE_ADMOB_ANDROID_BANNER_UNIT_ID,
      admob_android_interstitial_unit_id: process.env.GOOGLE_ADMOB_ANDROID_INTERSTITIAL_UNIT_ID,
    },
    meta: {
      pixel_id: process.env.META_PIXEL_ID,
      catalog_id: process.env.META_CATALOG_ID,
      business_id: process.env.META_BUSINESS_ID,
    },
    yandex: {
      metrica_id: process.env.YANDEX_METRICA_ID,
      verification: process.env.YANDEX_VERIFICATION,
      direct_counter_id: process.env.YANDEX_DIRECT_COUNTER_ID,
    },
    microsoft: {
      uet_tag_id: process.env.MICROSOFT_UET_TAG_ID,
      clarity_id: process.env.MICROSOFT_CLARITY_ID,
      bing_verification: process.env.BING_VERIFICATION,
    },
    tiktok: {
      pixel_id: process.env.TIKTOK_PIXEL_ID,
    },
  };
}
