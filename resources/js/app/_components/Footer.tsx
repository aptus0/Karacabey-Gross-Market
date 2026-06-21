"use client";

import Image from "next/image";
import Link from "next/link";
import { type ReactNode, useEffect, useState } from "react";
import {
  ArrowUpRight,
  ChevronDown,
  CreditCard,
  Lock,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  ShieldCheck,
  Tag,
  Truck,
} from "lucide-react";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { NavIcon } from "@/app/_components/NavIcon";
import { Button } from "@/app/_components/ui/button";
import { Separator } from "@/app/_components/ui/separator";
import { blogPosts } from "@/lib/blog";
import {
  defaultNavigation,
  fetchNavigation,
  type NavigationData,
  type NavigationItem,
} from "@/lib/navigation";

type FooterProps = {
  compact?: boolean;
};

type BrandLogo = {
  name: string;
  src?: string;
  initials?: string;
  tone?: "orange" | "green" | "blue" | "slate" | "red";
};

type BankLogoGroup = {
  title: string;
  banks: BrandLogo[];
};

const cardLogos: BrandLogo[] = [
  { name: "Visa", src: "/assets/brands/payment/visa-20260521.svg" },
  { name: "Mastercard", src: "/assets/brands/payment/mastercard-20260521.svg" },
  { name: "Troy", src: "/assets/brands/payment/troy.svg" },
  { name: "Bankkart", src: "/assets/brands/payment/bankkart-20260521.svg" },
  { name: "PayTR", src: "/assets/brands/payment/paytr.svg" },
];

const mealCardLogos: BrandLogo[] = [
  { name: "Pluxee/Sodexo", src: "/assets/brands/meal/sodexo-20260521.svg" },
  { name: "Multinet", src: "/assets/brands/meal/multinet-20260521.svg" },
  { name: "Edenred Ticket", src: "/assets/brands/meal/edenred-ticket-20260521.svg" },
  { name: "MetropolCard", src: "/assets/brands/meal/metropol-20260521.svg" },
  { name: "Setcard", initials: "SC", tone: "green" },
  { name: "TokenFlex", initials: "TF", tone: "blue" },
  { name: "Paye Kart", initials: "PK", tone: "orange" },
];

// BDDK authorized bank list checked on 2026-06-19; refresh before future launch changes.
const bankLogoGroups: BankLogoGroup[] = [
  {
    title: "Mevduat bankaları",
    banks: [
      { name: "Akbank", initials: "AK", tone: "red" },
      { name: "AlternatifBank", initials: "AB", tone: "blue" },
      { name: "Anadolubank", initials: "AN", tone: "blue" },
      { name: "Arap Türk Bankası", initials: "AT", tone: "green" },
      { name: "Bank Mellat", initials: "BM", tone: "red" },
      { name: "Bank of China Turkey", initials: "BC", tone: "red" },
      { name: "Burgan Bank", initials: "BU", tone: "blue" },
      { name: "Citibank", initials: "CI", tone: "blue" },
      { name: "Colendi Bank", initials: "CO", tone: "orange" },
      { name: "DenizBank", initials: "DB", tone: "blue" },
      { name: "Deutsche Bank", initials: "DE", tone: "blue" },
      { name: "Enpara Bank", initials: "EN", tone: "slate" },
      { name: "Fibabanka", initials: "FB", tone: "blue" },
      { name: "FUPS Bank", initials: "FU", tone: "orange" },
      { name: "HSBC Bank", initials: "HS", tone: "red" },
      { name: "ICBC Turkey Bank", initials: "IC", tone: "red" },
      { name: "ING Bank", initials: "IN", tone: "orange" },
      { name: "Intesa Sanpaolo", initials: "IS", tone: "green" },
      { name: "JP Morgan Chase Bank", initials: "JP", tone: "slate" },
      { name: "MUFG Bank Turkey", initials: "MU", tone: "red" },
      { name: "Odea Bank", initials: "OD", tone: "blue" },
      { name: "QNB Bank", initials: "QN", tone: "blue" },
      { name: "Rabobank", initials: "RA", tone: "orange" },
      { name: "Societe Generale", initials: "SG", tone: "red" },
      { name: "Şekerbank", initials: "SE", tone: "green" },
      { name: "Ziraat Bankası", initials: "ZB", tone: "red" },
      { name: "Turkish Bank", initials: "TB", tone: "blue" },
      { name: "Turkland Bank", initials: "TL", tone: "blue" },
      { name: "TEB", initials: "TE", tone: "green" },
      { name: "Türk Ticaret Bankası", initials: "TT", tone: "slate" },
      { name: "Garanti BBVA", initials: "GB", tone: "green" },
      { name: "Halkbank", initials: "HB", tone: "blue" },
      { name: "İş Bankası", initials: "IB", tone: "blue" },
      { name: "VakıfBank", initials: "VB", tone: "orange" },
      { name: "Yapı Kredi", initials: "YK", tone: "blue" },
      { name: "Ziraat Dinamik Banka", initials: "ZD", tone: "red" },
    ],
  },
  {
    title: "Kalkınma ve yatırım bankaları",
    banks: [
      { name: "Aktif Yatırım Bankası", initials: "AY", tone: "orange" },
      { name: "Aytemiz Yatırım Bankası", initials: "AT", tone: "red" },
      { name: "Bank of America Yatırım Bank", initials: "BA", tone: "red" },
      { name: "BankPozitif", initials: "BP", tone: "green" },
      { name: "D Yatırım Bankası", initials: "DY", tone: "slate" },
      { name: "Destek Yatırım Bankası", initials: "DE", tone: "blue" },
      { name: "Diler Yatırım Bankası", initials: "DI", tone: "slate" },
      { name: "Golden Global Yatırım Bankası", initials: "GG", tone: "orange" },
      { name: "GSD Yatırım Bankası", initials: "GS", tone: "blue" },
      { name: "Hedef Yatırım Bankası", initials: "HE", tone: "green" },
      { name: "İller Bankası", initials: "IL", tone: "green" },
      { name: "Takasbank", initials: "TA", tone: "slate" },
      { name: "Misyon Yatırım Bankası", initials: "MI", tone: "blue" },
      { name: "Nurol Yatırım Bankası", initials: "NU", tone: "orange" },
      { name: "PASHA Yatırım Bankası", initials: "PA", tone: "green" },
      { name: "Q Yatırım Bankası", initials: "QY", tone: "blue" },
      { name: "Standard Chartered", initials: "SC", tone: "green" },
      { name: "Tera Yatırım Bankası", initials: "TR", tone: "red" },
      { name: "Türk Eximbank", initials: "EX", tone: "blue" },
      { name: "Türkiye Kalkınma ve Yatırım Bankası", initials: "KY", tone: "green" },
      { name: "TSKB", initials: "TS", tone: "green" },
    ],
  },
  {
    title: "Katılım bankaları ve TMSF",
    banks: [
      { name: "Adil Katılım Bankası", initials: "AD", tone: "green" },
      { name: "Albaraka Türk", initials: "AL", tone: "green" },
      { name: "Dünya Katılım", initials: "DK", tone: "green" },
      { name: "Hayat Finans", initials: "HF", tone: "green" },
      { name: "Kuveyt Türk", initials: "KT", tone: "green" },
      { name: "T.O.M. Katılım Bankası", initials: "TO", tone: "blue" },
      { name: "Emlak Katılım", initials: "EK", tone: "green" },
      { name: "Türkiye Finans", initials: "TF", tone: "green" },
      { name: "Vakıf Katılım", initials: "VK", tone: "green" },
      { name: "Ziraat Katılım", initials: "ZK", tone: "green" },
      { name: "Birleşik Fon Bankası", initials: "BF", tone: "slate" },
    ],
  },
];

const footerNotes = [
  {
    icon: <Truck size={20} />,
    title: "Planlı teslimat operasyonu",
    description: "Karacabey merkez ve yakın bölgelerde düzenli sipariş akışı için tasarlandı.",
  },
  {
    icon: <ShieldCheck size={20} />,
    title: "Güvenli ödeme katmanı",
    description: "SSL, 3D Secure ve PayTR entegrasyonu ile checkout yüzeyi korunur.",
  },
  {
    icon: <Tag size={20} />,
    title: "Kurumsal ve bireysel kullanım",
    description: "Tekrarlı siparişler, kampanyalar ve hesap yönetimi aynı yapı içinde ilerler.",
  },
];

const whatsappDisplay = "065453458663";
const whatsappUrl = "https://wa.me/9065453458663";
const phoneDisplay = "(0224) 676 84 33";
const phoneUrl = "tel:+902246768433";
const addressDisplay = "Drama, Runguçpaşa Caddesi, 16700 Karacabey/Bursa";
const mapsUrl =
  "https://www.google.com/maps/search/?api=1&query=Drama%2C%20Rungu%C3%A7pa%C5%9Fa%20Caddesi%2C%2016700%20Karacabey%2FBursa";

/** URL'e göre tekrarlanan item'ları kaldırır. */
function dedupeByUrl<T extends { url: string }>(items: T[]): T[] {
  const seen = new Set<string>();
  return items.filter((item) => {
    if (seen.has(item.url)) return false;
    seen.add(item.url);
    return true;
  });
}

export function Footer({ compact = false }: FooterProps) {
  const [navigation, setNavigation] = useState<NavigationData>(defaultNavigation);
  const [email, setEmail] = useState("");

  useEffect(() => {
    const controller = new AbortController();
    fetchNavigation(controller.signal)
      .then(setNavigation)
      .catch(() => setNavigation(defaultNavigation));
    return () => controller.abort();
  }, []);

  const blogLinks = blogPosts.slice(0, 4).map((post) => ({
    label: post.title,
    url: `/blog/${post.slug}`,
    icon: "file-text" as const,
  }));

  return (
    <footer className={`${compact ? "site-footer site-footer--compact" : "site-footer"} hidden md:block`}>
      <div className={`mx-auto w-full max-w-[1440px] px-4 sm:px-6 lg:px-12 ${compact ? "py-10" : "py-14"}`}>
        <div className="grid gap-6 lg:gap-8">

          {/* ── DESKTOP ÜSTÜ BÖLÜM ── */}
          <div className="footer-desktop-top grid gap-8 lg:grid-cols-4 lg:gap-12">
            {/* Marka */}
            <section className="grid content-start gap-5">
              <Link className="inline-flex w-fit rounded-2xl border border-[#E4E7EB] bg-white p-4 shadow-sm" href="/">
                <KgmLogo />
              </Link>
              <div className="grid gap-2">
                <p className="text-xs font-black uppercase tracking-[0.2em] text-[#4B5E7A]">
                  Karacabey Gross Market
                </p>
                <h2 className="text-xl font-black leading-tight text-[#F1F5F9]">
                  Karacabey için güvenilir online market operasyonu.
                </h2>
                <p className="text-sm leading-7 text-[#94A3B8]">
                  Hızlı teslimat, güvenli ödeme, kurumsal sipariş akışı.
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <Button asChild className="h-10 rounded-xl px-5">
                  <Link href="/products">Ürünleri İncele</Link>
                </Button>
                <Button asChild variant="outline" className="h-10 rounded-xl border-[#2D3A52] px-5 text-[#F1F5F9] hover:bg-[#1A2744]">
                  <Link href="/kurumsal/hakkimizda">Kurumsal</Link>
                </Button>
              </div>
            </section>

            <section className="grid content-start gap-8">
              <FooterColumn
                title="Alışveriş"
                items={dedupeByUrl([
                  ...navigation.footer_primary,
                  { label: "Kampanyalar", url: "/kampanyalar", icon: "tag" },
                  { label: "Yeni Ürünler", url: "/products?sort=newest", icon: "grid" },
                  { label: "Mobil Uygulama (iOS)", url: "/mobile", icon: "smartphone" },
                ])}
              />
              <FooterColumn
                title="Kurumsal"
                items={dedupeByUrl([
                  ...navigation.footer_corporate,
                  { label: "Ödeme Güvenliği", url: "/kurumsal/odeme-guvenligi", icon: "shield" },
                  { label: "Teslimat Bölgeleri", url: "/kurumsal/teslimat-bolgeleri", icon: "map-pin" },
                  { label: "Mesafeli Satış", url: "/kurumsal/mesafeli-satis-sozlesmesi", icon: "file-text" },
                ])}
              />
            </section>

            <section className="grid content-start gap-8">
              <FooterColumn
                title="Destek"
                items={dedupeByUrl([
                  ...navigation.footer_support,
                  { label: "Sıkça Sorulan Sorular", url: "/sikca-sorulan-sorular", icon: "help-circle" },
                  { label: "Mobil Uygulama", url: "/mobile", icon: "smartphone" },
                  { label: "Üyelik Sözleşmesi", url: "/kurumsal/uyelik-sozlesmesi", icon: "file-text" },
                  { label: "Gizlilik Politikası", url: "/kurumsal/gizlilik-politikasi", icon: "shield" },
                  { label: "İletişim", url: "/kurumsal/iletisim", icon: "phone" },
                  { label: "KVKK", url: "/kurumsal/kvkk", icon: "shield" },
                ])}
              />
            </section>

            <section className="grid content-start gap-8">
              <FooterColumn
                title="Hesabım & Blog"
                items={dedupeByUrl([
                  ...navigation.footer_account,
                  { label: "Blog Ana Sayfa", url: "/blog", icon: "file-text" },
                  ...blogLinks,
                ])}
              />
            </section>
          </div>

          {/* ── MOBİL ACCORDION NAV ── */}
          <div className="footer-mobile-nav grid gap-1">
            <MobileAccordion title="Alışveriş & Kurumsal" items={dedupeByUrl([
              ...navigation.footer_primary,
              { label: "Kampanyalar", url: "/kampanyalar", icon: "tag" },
              ...navigation.footer_corporate,
              { label: "Mesafeli Satış", url: "/kurumsal/mesafeli-satis-sozlesmesi", icon: "file-text" },
            ])} />
            <MobileAccordion title="Destek & Hesap" items={dedupeByUrl([
              ...navigation.footer_support,
              { label: "SSS", url: "/sikca-sorulan-sorular", icon: "help-circle" },
              { label: "Mobil App", url: "/mobile", icon: "smartphone" },
              { label: "İletişim", url: "/kurumsal/iletisim", icon: "phone" },
              { label: "KVKK", url: "/kurumsal/kvkk", icon: "shield" },
              ...navigation.footer_account,
            ])} />
          </div>

          <Separator className="bg-[#2D3A52]" />

          {/* ── İLETİŞİM KARTLARI ── */}
          <div className="footer-contact-grid grid gap-4 rounded-2xl border border-[#2D3A52] bg-[#1A2744] p-4 sm:p-6 lg:grid-cols-[0.95fr_1fr_1fr_1.2fr] lg:p-8">
            <div className="grid content-start gap-3">
              <div className="inline-flex items-center gap-2 text-[#FF7A00]">
                <Phone size={18} />
                <span className="text-xs font-black uppercase tracking-[0.16em]">İletişim</span>
              </div>
              <h3 className="text-lg font-black text-[#F1F5F9]">Bize hızlıca ulaşın</h3>
              <p className="hidden text-sm leading-6 text-[#94A3B8] lg:block">
                WhatsApp, telefon ve adres bilgilerimizle her zaman yanınızdayız.
              </p>
              <div className="flex flex-wrap gap-2 pt-1">
                <Button asChild variant="outline" className="h-9 rounded-xl border-[#2D3A52] px-4 text-xs text-[#F1F5F9] hover:bg-[#243363]">
                  <Link href="/kurumsal/iletisim">İletişim</Link>
                </Button>
                <Button asChild className="h-9 rounded-xl px-4 text-xs">
                  <Link href={mapsUrl} target="_blank" rel="noreferrer">
                    Haritada Gör
                    <ArrowUpRight size={13} />
                  </Link>
                </Button>
              </div>
            </div>

            {/* Mobilde yatay scroll, desktop'ta grid */}
            <div className="footer-contact-cards col-span-full grid gap-3 sm:grid-cols-3 lg:col-span-3 lg:grid-cols-3">
              <ContactCard
                href={whatsappUrl}
                icon={<MessageCircle size={18} />}
                label="WhatsApp"
                value={whatsappDisplay}
                description="Yazılı iletişim ve hızlı bilgi."
                external
              />
              <ContactCard
                href={phoneUrl}
                icon={<Phone size={18} />}
                label="Telefon"
                value={phoneDisplay}
                description="Sipariş ve destek hattı."
              />
              <ContactCard
                href={mapsUrl}
                icon={<MapPin size={18} />}
                label="Adres"
                value={addressDisplay}
                description="Drama, Runguçpaşa Caddesi."
                external
              />
            </div>
          </div>

          <Separator className="bg-[#2D3A52]" />

          {/* ── BÜLTEN ── */}
          <div className="rounded-2xl border border-[#2D3A52] bg-[#1A2744] p-5 sm:p-8 lg:p-10">
            <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
              <div className="grid gap-2 lg:max-w-xl lg:gap-3">
                <div className="inline-flex items-center gap-2 text-[#FF7A00]">
                  <Mail size={18} />
                  <span className="text-xs font-black uppercase tracking-[0.16em]">Bülten</span>
                </div>
                <h3 className="text-lg font-black text-[#F1F5F9]">Kampanyalardan ilk sen haberdar ol</h3>
                <p className="hidden text-sm leading-6 text-[#94A3B8] sm:block">
                  Yeni ürünler ve özel fırsatlar için e-posta listemize katıl. Spam yok.
                </p>
              </div>
              <form
                className="flex w-full flex-col gap-3 sm:flex-row lg:w-auto"
                onSubmit={(event) => {
                  event.preventDefault();
                  setEmail("");
                }}
              >
                <input
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="E-posta adresiniz"
                  className="h-11 w-full rounded-xl border border-[#2D3A52] bg-[#0F1A2E] px-4 text-sm text-[#F1F5F9] placeholder:text-[#4B5E7A] focus:border-[#FF7A00] focus:outline-none focus:ring-2 focus:ring-[#FF7A00]/20 sm:w-72"
                  required
                />
                <Button type="submit" className="h-11 w-full rounded-xl px-6 sm:w-auto">
                  Abone Ol
                </Button>
              </form>
            </div>
          </div>

          <Separator className="bg-[#2D3A52]" />

          {/* ── TRUST NOTES — mobilde gizli ── */}
          <div className="footer-trust-notes hidden gap-4 sm:grid sm:grid-cols-3">
            {footerNotes.map((item) => (
              <div
                key={item.title}
                className="flex items-start gap-4 rounded-2xl border border-[#2D3A52] bg-[#1A2744] p-5"
              >
                <div className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FFF3E6] text-[#FF7A00]">
                  {item.icon}
                </div>
                <div className="grid gap-1">
                  <strong className="text-sm font-black text-[#F1F5F9]">{item.title}</strong>
                  <p className="text-sm leading-6 text-[#94A3B8]">{item.description}</p>
                </div>
              </div>
            ))}
          </div>

          <Separator className="footer-trust-notes hidden bg-[#2D3A52] sm:block" />

          {/* ── ÖDEME & GÜVENLİK ── */}
          <div className="footer-payments flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
              <span className="text-xs font-black uppercase tracking-[0.14em] text-[#94A3B8]">Ödeme</span>
              <div className="flex flex-wrap items-center gap-2">
                {cardLogos.map((card) => (
                  <BrandTile key={card.name} brand={card} />
                ))}
              </div>
              <span className="text-xs font-black uppercase tracking-[0.14em] text-[#94A3B8]">Yemek</span>
              <div className="flex flex-wrap items-center gap-2">
                {mealCardLogos.map((card) => (
                  <BrandTile key={card.name} brand={card} variant="light" />
                ))}
              </div>
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs font-black uppercase tracking-[0.14em] text-[#94A3B8]">Güvenlik</span>
              <div className="flex items-center gap-2">
                {[
                  { name: "SSL", icon: <Lock size={13} /> },
                  { name: "256-bit", icon: <ShieldCheck size={13} /> },
                  { name: "3D Secure", icon: <CreditCard size={13} /> },
                ].map((item) => (
                  <div
                    key={item.name}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-[#2D3A52] bg-[#1A2744] px-2.5 py-1.5 text-[#94A3B8]"
                  >
                    <span className="text-[#FF7A00]">{item.icon}</span>
                    <span className="text-xs font-semibold">{item.name}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="footer-bank-logos" aria-label="Türkiye'de faaliyet gösteren bankalar">
            <div className="footer-bank-logos__head">
              <div>
                <span>Banka ağı</span>
                <strong>Türkiye'de faaliyet gösteren bankalar</strong>
              </div>
              <p>BDDK yetkili kuruluş listesi referans alınarak bilgilendirme amaçlı gösterilir.</p>
            </div>

            <div className="footer-bank-logos__groups">
              {bankLogoGroups.map((group) => (
                <section key={group.title} className="footer-bank-logos__group" aria-label={group.title}>
                  <strong>{group.title}</strong>
                  <div>
                    {group.banks.map((bank) => (
                      <BrandTile key={`${group.title}-${bank.name}`} brand={bank} variant="bank" />
                    ))}
                  </div>
                </section>
              ))}
            </div>
          </div>

          <p className="text-xs leading-6 text-[#94A3B8]">
            Desteklenen ödeme alanında aktif kart ağları, PayTR güvenli ödeme altyapısı ve mağazada geçerli yemek kartları gösterilir. Banka listesi ödeme/taksit taahhüdü değildir; banka bazlı destek PayTR ve mağaza anlaşmalarına göre değişebilir.
          </p>

          <Separator className="bg-[#2D3A52]" />

          {/* ── COPYRIGHT ── */}
          <div className="flex flex-col gap-3 text-xs text-[#94A3B8] sm:flex-row sm:items-center sm:justify-between sm:text-sm">
            <p>© 2026 Karacabey Gross Market. Tüm hakları saklıdır.</p>
            <div className="flex flex-wrap gap-4">
              <Link href="/kurumsal/kvkk" className="transition-colors hover:text-[#FF7A00]">KVKK</Link>
              <Link href="/kurumsal/mesafeli-satis-sozlesmesi" className="transition-colors hover:text-[#FF7A00]">Mesafeli Satış</Link>
              <Link href="/kurumsal/odeme-guvenligi" className="transition-colors hover:text-[#FF7A00]">Ödeme Güvenliği</Link>
              <Link href="/kurumsal/iletisim" className="transition-colors hover:text-[#FF7A00]">İletişim</Link>
              <Link href="/blog" className="transition-colors hover:text-[#FF7A00]">Blog</Link>
            </div>
          </div>

        </div>
      </div>
    </footer>
  );
}

// ─── Desktop Footer Column ───────────────────────────────────────────────────
type FooterColumnProps = {
  title: string;
  items: Array<NavigationItem | { label: string; url: string; icon?: string | null }>;
};

function FooterColumn({ title, items }: FooterColumnProps) {
  return (
    <nav className="grid content-start gap-4" aria-label={title}>
      <strong className="text-sm font-black uppercase tracking-[0.16em] text-[#94A3B8]">{title}</strong>
      <div className="grid gap-2">
        {items.map((item, index) => (
          <Link
            key={`${index}-${item.url}`}
            href={item.url}
            target={"external" in item && item.external ? "_blank" : undefined}
            rel={"external" in item && item.external ? "noreferrer" : undefined}
            className="inline-flex items-start gap-2.5 rounded-lg px-1 py-1.5 text-sm font-semibold text-[#94A3B8] transition hover:bg-[#1A2744] hover:text-white"
          >
            <span className="mt-0.5 text-[#4B5E7A]">
              <NavIcon name={item.icon} size={15} />
            </span>
            <span className="line-clamp-2">{item.label}</span>
          </Link>
        ))}
      </div>
    </nav>
  );
}

// ─── Mobile Accordion Nav ────────────────────────────────────────────────────
type MobileAccordionProps = {
  title: string;
  items: Array<{ label: string; url: string; icon?: string | null }>;
};

function MobileAccordion({ title, items }: MobileAccordionProps) {
  const [open, setOpen] = useState(false);

  return (
    <div className="overflow-hidden rounded-2xl border border-[#2D3A52] bg-[#1A2744]">
      <button
        type="button"
        className="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left"
        aria-expanded={open}
        onClick={() => setOpen((prev) => !prev)}
      >
        <span className="text-sm font-black text-[#F1F5F9]">{title}</span>
        <ChevronDown
          size={16}
          className="shrink-0 text-[#94A3B8] transition-transform duration-200"
          style={{ transform: open ? "rotate(180deg)" : "rotate(0deg)" }}
        />
      </button>

      {open && (
        <div className="grid grid-cols-2 gap-1 border-t border-[#2D3A52] px-3 pb-3 pt-2">
          {items.map((item, index) => (
            <Link
              key={`mobile-${index}-${item.url}`}
              href={item.url}
              className="inline-flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#94A3B8] transition hover:text-white active:text-[#FF7A00]"
            >
              <span className="text-[#FF7A00]">
                <NavIcon name={item.icon} size={14} />
              </span>
              <span className="line-clamp-1">{item.label}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

function BrandTile({
  brand,
  variant = "dark",
}: {
  brand: BrandLogo;
  variant?: "dark" | "light" | "bank";
}) {
  const tileClassName = [
    "footer-brand-tile",
    `footer-brand-tile--${variant}`,
    brand.tone ? `footer-brand-tile--${brand.tone}` : "",
  ].filter(Boolean).join(" ");

  return (
    <span className={tileClassName} title={brand.name} aria-label={brand.name}>
      {brand.src ? (
        <Image
          src={brand.src}
          alt={brand.name}
          width={48}
          height={28}
          className="h-auto max-h-[18px] w-auto"
        />
      ) : (
        <>
          <span className="footer-brand-tile__mark" aria-hidden="true">{brand.initials ?? brand.name.slice(0, 2)}</span>
          <span className="footer-brand-tile__name">{brand.name}</span>
        </>
      )}
    </span>
  );
}

// ─── Contact Card ────────────────────────────────────────────────────────────
function ContactCard({
  href,
  icon,
  label,
  value,
  description,
  external = false,
}: {
  href: string;
  icon: ReactNode;
  label: string;
  value: string;
  description: string;
  external?: boolean;
}) {
  return (
    <Link
      href={href}
      target={external ? "_blank" : undefined}
      rel={external ? "noreferrer" : undefined}
      className="grid content-start gap-3 rounded-2xl border border-[#2D3A52] bg-[#1A2744] p-4 transition hover:border-[#FF7A00] hover:bg-[#1F2F4A] active:bg-[#243363]"
    >
      <div className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFF3E6] text-[#FF7A00]">
        {icon}
      </div>
      <div className="grid gap-0.5">
        <span className="text-xs font-black uppercase tracking-[0.16em] text-[#4B5E7A]">{label}</span>
        <strong className="text-sm font-black leading-5 text-[#F1F5F9]">{value}</strong>
        <span className="text-xs leading-5 text-[#94A3B8]">{description}</span>
      </div>
    </Link>
  );
}
