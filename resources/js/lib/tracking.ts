"use client";

import {
  createClientUID,
  clientIdentityHeaders,
  getStoredCustomerUID,
  getStoredSessionUID,
  getStoredCartToken,
} from "@/lib/api";
import {
  type ConsentCategory,
  type CookieConsentPreferences,
  defaultConsent,
  getStoredConsent,
} from "@/lib/consent";

export type TrackingEventName =
  | "page_view"
  | "view_item"
  | "view_category"
  | "search"
  | "select_item"
  | "add_to_cart"
  | "remove_from_cart"
  | "view_cart"
  | "begin_checkout"
  | "add_shipping_info"
  | "add_payment_info"
  | "purchase"
  | "refund"
  | "login"
  | "register"
  | "wishlist_add"
  | "coupon_apply"
  | "coupon_remove"
  | "cta_click"
  | "banner_click"
  | "promotion_view"
  | "promotion_click"
  | "consent_update";

type TrackOptions = {
  category?: ConsentCategory;
  value_cents?: number;
  currency?: string;
  product_id?: number | string | null;
  order_id?: number | string | null;
  campaign?: string | null;
};

type TrackingPayload = {
  event_id: string;
  event_name: TrackingEventName | string;
  category: ConsentCategory;
  anonymous_id: string | null;
  session_id: string | null;
  cart_token: string | null;
  page_url: string | null;
  referrer: string | null;
  source: string | null;
  medium: string | null;
  campaign: string | null;
  product_id: number | string | null;
  order_id: number | string | null;
  value_cents: number | null;
  currency: string;
  consent: CookieConsentPreferences;
  event_data: Record<string, unknown>;
  occurred_at: string;
};

const EVENT_CATEGORY: Partial<Record<TrackingEventName, ConsentCategory>> = {
  consent_update: "necessary",
  add_to_cart: "analytics",
  remove_from_cart: "analytics",
  view_cart: "analytics",
  begin_checkout: "analytics",
  purchase: "analytics",
  coupon_apply: "analytics",
  coupon_remove: "analytics",
  cta_click: "analytics",
  banner_click: "analytics",
  promotion_view: "marketing",
  promotion_click: "marketing",
};

export function hasConsent(category: ConsentCategory, consent = getStoredConsent()) {
  if (category === "necessary") return true;
  if (!consent) return false;
  return Boolean(consent[category]);
}

export function track(
  eventName: TrackingEventName | string,
  eventData: Record<string, unknown> = {},
  options: TrackOptions = {},
) {
  if (typeof window === "undefined") return;

  const resolvedName = eventName as TrackingEventName;
  const category = options.category ?? EVENT_CATEGORY[resolvedName] ?? "analytics";
  const consent = getStoredConsent();
  if (!hasConsent(category, consent)) return;

  const url = new URL(window.location.href);
  const params = url.searchParams;
  const currentConsent = consent ?? defaultConsent();
  const payload: TrackingPayload = {
    event_id: createClientUID("evt"),
    event_name: eventName,
    category,
    anonymous_id: getStoredCustomerUID(),
    session_id: getStoredSessionUID(),
    cart_token: getStoredCartToken(),
    page_url: window.location.href,
    referrer: document.referrer || null,
    source: (eventData.source as string | undefined) ?? params.get("utm_source"),
    medium: (eventData.medium as string | undefined) ?? params.get("utm_medium"),
    campaign: options.campaign ?? (eventData.campaign as string | undefined) ?? params.get("utm_campaign"),
    product_id: options.product_id ?? (eventData.product_id as number | string | undefined) ?? null,
    order_id: options.order_id ?? (eventData.order_id as number | string | undefined) ?? null,
    value_cents: typeof options.value_cents === "number" ? options.value_cents : null,
    currency: options.currency ?? "TRY",
    consent: currentConsent,
    event_data: eventData,
    occurred_at: new Date().toISOString(),
  };

  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({
    event: eventName,
    kgm_event_id: payload.event_id,
    kgm_category: category,
    ...eventData,
  });

  void sendTrackingPayload(payload);
}

async function sendTrackingPayload(payload: TrackingPayload) {
  try {
    await fetch("/api/tracking/events", {
      method: "POST",
      credentials: "include",
      keepalive: true,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...clientIdentityHeaders(),
      },
      body: JSON.stringify(payload),
    });
  } catch {
    // Analytics must never break shopping flows.
  }
}

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
  }
}
