"use client";

import { useEffect } from "react";
import { useSearchParams } from "next/navigation";
import { useAuthStore } from "@/lib/auth-store";
import { hasConsent, track } from "@/lib/tracking";

type CheckoutConversionTrackerProps = {
  googleAdsId?: string;
  googleAdsConversionLabel?: string;
};

export function CheckoutConversionTracker({
  googleAdsId,
  googleAdsConversionLabel,
}: CheckoutConversionTrackerProps) {
  const searchParams = useSearchParams();
  const { hasInitializedRemote, isHydrated, user } = useAuthStore((state) => ({
    hasInitializedRemote: state.hasInitializedRemote,
    isHydrated: state.isHydrated,
    user: state.user,
  }));

  useEffect(() => {
    if (!isHydrated || !hasInitializedRemote || user?.ad_free || user?.is_vip) return;
    if (!googleAdsId || !googleAdsConversionLabel || !hasConsent("marketing")) return;

    const merchantOid = firstParam(searchParams, ["merchant_oid", "merchantOid", "oid", "order_id"]);
    const valueCents = centsParam(searchParams);
    const dedupeSource = merchantOid ?? (searchParams.toString() || "unknown");
    const dedupeKey = `kgm:checkout-success:conversion:${dedupeSource}`;

    if (sessionStorage.getItem(dedupeKey)) return;
    sessionStorage.setItem(dedupeKey, new Date().toISOString());

    const payload: Record<string, string | number | undefined> = {
      send_to: `${googleAdsId}/${googleAdsConversionLabel}`,
      transaction_id: merchantOid ?? undefined,
      currency: "TRY",
    };
    if (typeof valueCents === "number") {
      payload.value = Number((valueCents / 100).toFixed(2));
    }

    window.gtag?.("event", "conversion", payload);

    track(
      "purchase",
      {
        source: "checkout_success",
        google_ads_conversion: true,
        merchant_oid: merchantOid,
      },
      {
        category: "marketing",
        order_id: merchantOid,
        value_cents: valueCents ?? undefined,
        currency: "TRY",
      },
    );
  }, [googleAdsConversionLabel, googleAdsId, hasInitializedRemote, isHydrated, searchParams, user?.ad_free, user?.is_vip]);

  return null;
}

type ReadonlySearchParams = Pick<URLSearchParams, "get" | "toString">;

function firstParam(searchParams: ReadonlySearchParams, keys: string[]) {
  for (const key of keys) {
    const value = searchParams.get(key);
    if (value) return value;
  }
  return null;
}

function centsParam(searchParams: ReadonlySearchParams) {
  for (const key of ["total_cents", "amount_cents", "payment_amount", "total_amount"]) {
    const value = Number(searchParams.get(key));
    if (Number.isFinite(value) && value > 0) return Math.round(value);
  }
  return null;
}
