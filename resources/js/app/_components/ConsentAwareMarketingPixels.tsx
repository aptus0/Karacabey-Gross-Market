"use client";

import Script from "next/script";
import { useEffect, useState } from "react";
import type { MarketingConfig } from "@/lib/marketing";
import { useAuthStore } from "@/lib/auth-store";
import {
  applyGoogleConsentMode,
  consentToGoogleMode,
  getStoredConsent,
  subscribeToConsentChanges,
  type CookieConsentPreferences,
} from "@/lib/consent";

type ConsentAwareMarketingPixelsProps = {
  config: MarketingConfig;
};

export function ConsentAwareMarketingPixels({ config }: ConsentAwareMarketingPixelsProps) {
  const [consent, setConsent] = useState<CookieConsentPreferences | null>(null);
  const { hasInitializedRemote, isHydrated, user } = useAuthStore((state) => ({
    hasInitializedRemote: state.hasInitializedRemote,
    isHydrated: state.isHydrated,
    user: state.user,
  }));

  useEffect(() => {
    let active = true;
    queueMicrotask(() => {
      if (!active) return;
      const current = getStoredConsent();
      setConsent(current);
      applyGoogleConsentMode(current);
    });

    const unsubscribe = subscribeToConsentChanges((next) => {
      setConsent(next);
      applyGoogleConsentMode(next);
    });

    return () => {
      active = false;
      unsubscribe();
    };
  }, []);

  if (!config.tracking_enabled || !isHydrated || !hasInitializedRemote || user?.ad_free || user?.is_vip) return null;

  const { google, meta, yandex, microsoft, tiktok } = config;
  const canAnalytics = Boolean(consent?.analytics || consent?.performance);
  const canMarketing = Boolean(consent?.marketing);
  const canPerformance = Boolean(consent?.performance);
  const canLoadGoogleBase = canAnalytics || canMarketing;
  const gtmId = google?.gtm_id;
  const gaId = canAnalytics ? google?.analytics_id : undefined;
  const adsId = canMarketing ? google?.ads_id : undefined;
  const metaPixelId = canMarketing ? meta?.pixel_id : undefined;
  const yandexId = canAnalytics || canPerformance ? yandex?.metrica_id : undefined;
  const uetId = canMarketing ? microsoft?.uet_tag_id : undefined;
  const clarityId = canPerformance ? microsoft?.clarity_id : undefined;
  const tiktokPixel = canMarketing ? tiktok?.pixel_id : undefined;

  return (
    <>
      <Script id="kgm-consent-default" strategy="afterInteractive">
        {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
          gtag('consent','default',${JSON.stringify(consentToGoogleMode(consent))});`}
      </Script>

      {gtmId && canLoadGoogleBase ? (
        <Script id="gtm-init" strategy="afterInteractive">
          {`(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','${gtmId}');`}
        </Script>
      ) : null}

      {gaId || adsId ? (
        <>
          <Script
            src={`https://www.googletagmanager.com/gtag/js?id=${gaId ?? adsId}`}
            strategy="afterInteractive"
          />
          <Script id="gtag-init" strategy="afterInteractive">
            {`window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('consent','update',${JSON.stringify(consentToGoogleMode(consent))});
              ${gaId ? `gtag('config', '${gaId}', { send_page_view: true });` : ""}
              ${adsId ? `gtag('config', '${adsId}');` : ""}`}
          </Script>
        </>
      ) : null}

      {metaPixelId ? (
        <Script id="meta-pixel" strategy="afterInteractive">
          {`!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('consent','grant'); fbq('init','${metaPixelId}'); fbq('track','PageView');`}
        </Script>
      ) : null}

      {yandexId ? (
        <Script id="yandex-metrica" strategy="afterInteractive">
          {`(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){
            if(document.scripts[j].src===r){return}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],
            k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script',
            'https://mc.yandex.ru/metrika/tag.js','ym');
            ym(${yandexId}, 'init', { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true });`}
        </Script>
      ) : null}

      {uetId ? (
        <Script id="microsoft-uet" strategy="afterInteractive">
          {`(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"${uetId}"};
            o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,
            n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;
            s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},
            i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})
            (window,document,"script","https://bat.bing.com/bat.js","uetq");`}
        </Script>
      ) : null}

      {clarityId ? (
        <Script id="ms-clarity" strategy="afterInteractive">
          {`(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "${clarityId}");`}
        </Script>
      ) : null}

      {tiktokPixel ? (
        <Script id="tiktok-pixel" strategy="afterInteractive">
          {`!function (w, d, t) {w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
            ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],
            ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
            for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
            ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};
            ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";
            ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;
            ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement("script");
            o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;
            var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
            ttq.load('${tiktokPixel}'); ttq.page();}(window, document, 'ttq');`}
        </Script>
      ) : null}

      {gtmId && canLoadGoogleBase ? (
        <noscript>
          <iframe
            src={`https://www.googletagmanager.com/ns.html?id=${gtmId}`}
            height="0"
            width="0"
            style={{ display: "none", visibility: "hidden" }}
          />
        </noscript>
      ) : null}
    </>
  );
}
