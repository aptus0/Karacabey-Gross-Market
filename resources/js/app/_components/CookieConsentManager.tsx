"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { BarChart3, Megaphone, Settings2, ShieldCheck, SlidersHorizontal, Sparkles, Zap } from "lucide-react";
import { clientIdentityHeaders } from "@/lib/api";
import {
  type ConsentCategory,
  type CookieConsentPreferences,
  createConsent,
  defaultConsent,
  getStoredConsent,
  saveConsent,
  subscribeToConsentChanges,
} from "@/lib/consent";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";

type EditableCategory = Exclude<ConsentCategory, "necessary">;

const categories: Array<{
  key: ConsentCategory;
  title: string;
  description: string;
  locked?: boolean;
  icon: typeof ShieldCheck;
}> = [
  {
    key: "necessary",
    title: "Zorunlu",
    description: "Sepet, oturum, güvenlik ve ödeme akışı için gerekir.",
    locked: true,
    icon: ShieldCheck,
  },
  {
    key: "analytics",
    title: "Analitik",
    description: "Sayfa, ürün, arama ve sepet performansını ölçer.",
    icon: BarChart3,
  },
  {
    key: "marketing",
    title: "Pazarlama",
    description: "Google, Meta ve kampanya dönüşüm ölçümlerini yönetir.",
    icon: Megaphone,
  },
  {
    key: "personalization",
    title: "Kişiselleştirme",
    description: "Son gezilenler ve öneri deneyimini iyileştirir.",
    icon: Sparkles,
  },
  {
    key: "performance",
    title: "Performans",
    description: "Hız, hata ve deneyim kalitesini takip eder.",
    icon: Zap,
  },
];

export function CookieConsentManager() {
  const pathname = usePathname();
  const [storedConsent, setStoredConsent] = useState<CookieConsentPreferences | null>(null);
  const [draft, setDraft] = useState<CookieConsentPreferences>(() => defaultConsent());
  const [preferencesOpen, setPreferencesOpen] = useState(false);
  const [mounted, setMounted] = useState(false);

  const showBanner = mounted && !storedConsent;

  const pageKey = useMemo(
    () => `${pathname}${typeof window !== "undefined" ? window.location.search : ""}`,
    [pathname],
  );

  useEffect(() => {
    let active = true;
    queueMicrotask(() => {
      if (!active) return;
      const consent = getStoredConsent();
      setStoredConsent(consent);
      setDraft(consent ?? defaultConsent());
      setMounted(true);
    });

    const unsubscribe = subscribeToConsentChanges((next) => {
      setStoredConsent(next);
      setDraft(next);
    });

    return () => {
      active = false;
      unsubscribe();
    };
  }, []);

  useEffect(() => {
    if (!mounted) return;
    track("page_view", {
      path: pathname,
      title: typeof document !== "undefined" ? document.title : "",
    });
  }, [mounted, pageKey, pathname]);

  function updateDraft(key: EditableCategory, value: boolean) {
    setDraft((prev) => ({
      ...prev,
      [key]: value,
    }));
  }

  function persistConsent(next: CookieConsentPreferences, source: "accept_all" | "reject_all" | "preferences") {
    saveConsent(next);
    setStoredConsent(next);
    setDraft(next);
    setPreferencesOpen(false);
    void syncConsent(next, source);
    track("consent_update", { source, consent: next }, { category: "necessary" });
    if (next.analytics || next.performance) {
      track("page_view", {
        path: pathname,
        title: typeof document !== "undefined" ? document.title : "",
        reason: "consent_granted",
      });
    }
  }

  function acceptAll() {
    persistConsent(
      createConsent({
        analytics: true,
        marketing: true,
        personalization: true,
        performance: true,
      }),
      "accept_all",
    );
  }

  function rejectAll() {
    persistConsent(createConsent({}), "reject_all");
  }

  function savePreferences() {
    persistConsent(
      createConsent({
        analytics: draft.analytics,
        marketing: draft.marketing,
        personalization: draft.personalization,
        performance: draft.performance,
      }),
      "preferences",
    );
  }

  return (
    <>
      {showBanner ? (
        <div className="kgm-cookie-banner" role="dialog" aria-label="Çerez tercihleri">
          <div className="kgm-cookie-banner__copy">
            <span className="kgm-cookie-banner__icon">
              <ShieldCheck size={18} />
            </span>
            <div>
              <p className="kgm-cookie-banner__title">Çerez tercihleri</p>
              <p className="kgm-cookie-banner__text">
                Alışveriş deneyimini, sepet güvenliğini ve kampanya ölçümünü izinlerine göre yönetiyoruz.
              </p>
            </div>
          </div>
          <div className="kgm-cookie-banner__actions">
            <button type="button" className="kgm-cookie-btn kgm-cookie-btn--ghost" onClick={() => setPreferencesOpen(true)}>
              <Settings2 size={15} />
              Tercihler
            </button>
            <button type="button" className="kgm-cookie-btn kgm-cookie-btn--soft" onClick={rejectAll}>
              Reddet
            </button>
            <button type="button" className="kgm-cookie-btn kgm-cookie-btn--primary" onClick={acceptAll}>
              Kabul Et
            </button>
          </div>
        </div>
      ) : null}

      {preferencesOpen ? (
        <div className="kgm-cookie-modal" role="dialog" aria-modal="true" aria-label="Çerez tercih paneli">
          <button type="button" className="kgm-cookie-modal__shade" onClick={() => setPreferencesOpen(false)} aria-label="Kapat" />
          <div className="kgm-cookie-modal__panel">
            <div className="kgm-cookie-modal__head">
              <div>
                <p className="kgm-cookie-modal__eyebrow">Commerce Intelligence</p>
                <h2>Çerez ve takip tercihleri</h2>
              </div>
              <SlidersHorizontal size={20} />
            </div>

            <div className="kgm-cookie-category-list">
              {categories.map((category) => {
                const Icon = category.icon;
                const checked = Boolean(draft[category.key]);

                return (
                  <div className="kgm-cookie-category" key={category.key}>
                    <span className="kgm-cookie-category__icon">
                      <Icon size={17} />
                    </span>
                    <div className="kgm-cookie-category__body">
                      <p>{category.title}</p>
                      <span>{category.description}</span>
                    </div>
                    <button
                      type="button"
                      className={cn("kgm-cookie-toggle", checked && "is-on", category.locked && "is-locked")}
                      onClick={() => {
                        if (!category.locked) updateDraft(category.key as EditableCategory, !checked);
                      }}
                      aria-pressed={checked}
                      disabled={category.locked}
                    >
                      <span />
                    </button>
                  </div>
                );
              })}
            </div>

            <div className="kgm-cookie-modal__links">
              <Link href="/cerez-politikasi">Çerez Politikası</Link>
              <Link href="/kvkk">KVKK Aydınlatma Metni</Link>
              <Link href="/gizlilik-politikasi">Gizlilik Politikası</Link>
            </div>

            <div className="kgm-cookie-modal__actions">
              <button type="button" className="kgm-cookie-btn kgm-cookie-btn--soft" onClick={rejectAll}>
                Tümünü Reddet
              </button>
              <button type="button" className="kgm-cookie-btn kgm-cookie-btn--ghost" onClick={savePreferences}>
                Tercihleri Kaydet
              </button>
              <button type="button" className="kgm-cookie-btn kgm-cookie-btn--primary" onClick={acceptAll}>
                Tümünü Kabul Et
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

async function syncConsent(consent: CookieConsentPreferences, source: string) {
  try {
    await fetch("/api/tracking/consent", {
      method: "POST",
      credentials: "include",
      keepalive: true,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...clientIdentityHeaders(),
      },
      body: JSON.stringify({ source, consent }),
    });
  } catch {
    // Consent is already stored locally; server sync can retry on the next update.
  }
}
