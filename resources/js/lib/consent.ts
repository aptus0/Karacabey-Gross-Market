"use client";

export const CONSENT_STORAGE_KEY = "kgm_cookie_consent_v1";
export const CONSENT_EVENT_NAME = "kgm:consent-change";
export const CONSENT_VERSION = "2026-05-28";

export type ConsentCategory =
  | "necessary"
  | "analytics"
  | "marketing"
  | "personalization"
  | "performance";

export type CookieConsentPreferences = Record<ConsentCategory, boolean> & {
  version: string;
  updated_at: string;
};

export function defaultConsent(): CookieConsentPreferences {
  return {
    version: CONSENT_VERSION,
    necessary: true,
    analytics: false,
    marketing: false,
    personalization: false,
    performance: false,
    updated_at: new Date().toISOString(),
  };
}

export function createConsent(
  preferences: Partial<Record<Exclude<ConsentCategory, "necessary">, boolean>>,
): CookieConsentPreferences {
  return {
    ...defaultConsent(),
    analytics: Boolean(preferences.analytics),
    marketing: Boolean(preferences.marketing),
    personalization: Boolean(preferences.personalization),
    performance: Boolean(preferences.performance),
  };
}

export function getStoredConsent(): CookieConsentPreferences | null {
  if (typeof window === "undefined") return null;

  try {
    const raw = window.localStorage.getItem(CONSENT_STORAGE_KEY);
    if (!raw) return null;

    const parsed = JSON.parse(raw) as Partial<CookieConsentPreferences>;
    if (parsed.version !== CONSENT_VERSION) return null;

    return {
      version: CONSENT_VERSION,
      necessary: true,
      analytics: Boolean(parsed.analytics),
      marketing: Boolean(parsed.marketing),
      personalization: Boolean(parsed.personalization),
      performance: Boolean(parsed.performance),
      updated_at: typeof parsed.updated_at === "string" ? parsed.updated_at : new Date().toISOString(),
    };
  } catch {
    return null;
  }
}

export function saveConsent(consent: CookieConsentPreferences) {
  if (typeof window === "undefined") return;

  const next = {
    ...consent,
    version: CONSENT_VERSION,
    necessary: true,
    updated_at: new Date().toISOString(),
  };

  try {
    window.localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify(next));
  } catch {
    // Storage may be blocked; the in-page event still lets current session behave correctly.
  }

  applyGoogleConsentMode(next);
  window.dispatchEvent(new CustomEvent(CONSENT_EVENT_NAME, { detail: next }));
}

export function subscribeToConsentChanges(callback: (consent: CookieConsentPreferences) => void) {
  if (typeof window === "undefined") return () => undefined;

  const listener = (event: Event) => {
    const detail = event instanceof CustomEvent ? event.detail : null;
    if (detail) callback(detail as CookieConsentPreferences);
  };

  window.addEventListener(CONSENT_EVENT_NAME, listener);
  return () => window.removeEventListener(CONSENT_EVENT_NAME, listener);
}

export function consentToGoogleMode(consent: CookieConsentPreferences | null) {
  const current = consent ?? defaultConsent();
  const analytics = current.analytics || current.performance ? "granted" : "denied";
  const ads = current.marketing ? "granted" : "denied";

  return {
    analytics_storage: analytics,
    ad_storage: ads,
    ad_user_data: ads,
    ad_personalization: ads,
    functionality_storage: current.personalization ? "granted" : "denied",
    personalization_storage: current.personalization ? "granted" : "denied",
    security_storage: "granted",
  } as const;
}

export function applyGoogleConsentMode(consent: CookieConsentPreferences | null) {
  if (typeof window === "undefined") return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtagShim(...args: unknown[]) {
    window.dataLayer?.push(args);
  };
  window.gtag("consent", "update", consentToGoogleMode(consent));
}

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
  }
}
