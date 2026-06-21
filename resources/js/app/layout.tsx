import type { Metadata, Viewport } from "next";
import { Roboto } from "next/font/google";
import { MarketingPixels } from "@/app/_components/MarketingPixels";
import { CartNotification } from "@/app/_components/CartNotification";
import { CampaignModal } from "@/app/_components/CampaignModal";
import { CookieConsentManager } from "@/app/_components/CookieConsentManager";
import { OfflineNotice } from "@/app/_components/OfflineNotice";
import { SeoHead } from "@/app/_components/SeoHead";
import { Providers } from "@/app/providers";
import { getMarketingConfig } from "@/lib/marketing";
import {
  buildMetadata,
  groceryStoreSchema,
  jsonLdGraph,
  organizationSchema,
  siteUrl,
  websiteSchema,
} from "@/lib/seo";
import "./globals.css";

const roboto = Roboto({
  subsets: ["latin"],
  weight: ["300", "400", "500", "700", "900"],
  display: "swap",
  variable: "--font-sans-roboto",
});

export async function generateMetadata(): Promise<Metadata> {
  const marketing = await getMarketingConfig();
  const { google, yandex, microsoft } = marketing;

  return {
    metadataBase: new URL(siteUrl),
    ...buildMetadata({
      title: "Karacabey Gross Market",
      description:
        "Karacabey Gross Market — Karacabey ve Bursa'nın online gross marketi. Hızlı teslimat, güvenli ödeme, yerel ürünler. karacabeygrossmarket.com",
      path: "/",
      keywords: [
        "karacabeygrossmarket",
        "karacabey gross market",
        "Karacabey market",
        "yerel ürün alışverişi",
        "online market deneyimi",
      ],
    }),
    applicationName: "Karacabey Gross Market",
    manifest: "/manifest.webmanifest",
    icons: {
      icon: "/favicon.ico",
      shortcut: "/favicon.ico",
      apple: "/assets/kgm-favicon-256.png",
    },
    verification: {
      google: google?.site_verification ?? process.env.GOOGLE_SITE_VERIFICATION,
      yandex: yandex?.verification ?? process.env.YANDEX_SITE_VERIFICATION ?? process.env.YANDEX_WEBMASTER_VERIFICATION,
      other: {
        "msvalidate.01": microsoft?.bing_verification
          ?? process.env.BING_SITE_VERIFICATION
          ?? process.env.MSVALIDATE_01
          ?? "",
        "facebook-domain-verification": process.env.FACEBOOK_DOMAIN_VERIFICATION ?? "",
        "p:domain_verify": process.env.PINTEREST_DOMAIN_VERIFICATION ?? "",
      },
    },
    other: {
      "google": "notranslate",
      "msapplication-TileColor": "#111827",
      "msapplication-config": "/browserconfig.xml",
    },
    formatDetection: {
      telephone: false,
      date: false,
      address: false,
      email: false,
    },
  };
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#eef1f4" },
    { media: "(prefers-color-scheme: dark)", color: "#111827" },
  ],
  colorScheme: "light",
};

const siteJsonLd = jsonLdGraph([
  organizationSchema(),
  groceryStoreSchema(),
  websiteSchema(),
]);

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="tr">
      <body className={`s0 ${roboto.variable}`}>
        <Providers>
          <SeoHead data={siteJsonLd} />
          {children}
          <OfflineNotice />
          <CartNotification />
          <CampaignModal />
          <CookieConsentManager />
          <MarketingPixels />
        </Providers>
      </body>
    </html>
  );
}
