"use client";

import Link from "next/link";
import {
  Apple,
  ArrowRight,
  Baby,
  Beef,
  ChevronRight,
  CupSoda,
  Grid3X3,
  Heart,
  Home,
  Leaf,
  Milk,
  Package,
  ShoppingBag,
  Snowflake,
  Sparkles,
  Store,
  Tag,
  Truck,
  Wheat,
} from "lucide-react";
import { useEffect, useState } from "react";

import { ProductCard } from "@/app/_components/ProductCard";
import type { KgmCategory, KgmProduct } from "@/lib/catalog";
import type { HomepageBlock } from "@/lib/homepage";

const fallbackCategories: KgmCategory[] = [
  { name: "Meyve & Sebze", slug: "meyve-sebze", count: 479 },
  { name: "Et, Tavuk & Şarküteri", slug: "et-tavuk-sarkuteri", count: 155 },
  { name: "Süt Ürünleri & Kahvaltılık", slug: "sut-kahvaltilik", count: 639 },
  { name: "Fırın & Pastane", slug: "firin-pastane", count: 87 },
  { name: "Temel Gıda", slug: "temel-gida", count: 890 },
  { name: "Atıştırmalık & Çikolata", slug: "atistirmalik", count: 1140 },
  { name: "İçecekler", slug: "icecek", count: 966 },
  { name: "Dondurulmuş & Hazır Gıda", slug: "donmus-hazir", count: 105 },
  { name: "Temizlik", slug: "temizlik", count: 1284 },
  { name: "Kişisel Bakım", slug: "kisisel-bakim", count: 2225 },
  { name: "Bebek", slug: "bebek", count: 222 },
  { name: "Ev & Yaşam", slug: "ev-yasam", count: 516 },
  { name: "Diğer Ürünler", slug: "diger", count: 3479 },
];

const categoryIcons: Record<string, typeof Grid3X3> = {
  "meyve-sebze": Apple,
  "et-tavuk-sarkuteri": Beef,
  sarkuteri: Beef,
  "et-tavuk-balik": Beef,
  "et-tavuk": Beef,
  "sut-kahvaltilik": Milk,
  "sut-kahvalti": Milk,
  "firin-pastane": Wheat,
  "temel-gida": Package,
  atistirmalik: ShoppingBag,
  "atistirmalik-icecek": ShoppingBag,
  atistirmali: ShoppingBag,
  icecek: CupSoda,
  "donmus-hazir": Snowflake,
  "temizlik-urunleri": Sparkles,
  temizlik: Sparkles,
  "kisisel-bakim": Heart,
  "kozmetik-bakim": Heart,
  bebek: Baby,
  "zuccaciye-mutfak": Grid3X3,
  "ev-yasam": Home,
  diger: Grid3X3,
};

type BrandLogo = {
  name: string;
  src: string;
};

const brandLogoCatalog: BrandLogo[] = [
  { name: "Ülker", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/%C3%9Clker%20logo.svg" },
  { name: "Eti", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Eti%20logo.png" },
  { name: "Sütaş", src: "https://www.sutas.com/assets/img/sutas-logo-en.png" },
  { name: "Coca-Cola", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Coca-Cola_logo.svg" },
  { name: "Pepsi", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Pepsi_logo_2014.svg" },
  { name: "Nestlé", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Nestl%C3%A9_textlogo.svg" },
  { name: "Barilla", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Barilla_logo.svg" },
  { name: "Lipton", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Lipton_logo_(2014-present).svg" },
  { name: "Danone", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Danone_Logo.png" },
  { name: "Sprite", src: "https://commons.wikimedia.org/wiki/Special:Redirect/file/Sprite_2022.svg" },
];

function normalizeBrandKey(value: string) {
  return value
    .trim()
    .toLocaleLowerCase("tr-TR")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "");
}

const brandLogoByName = new Map(brandLogoCatalog.map((brand) => [normalizeBrandKey(brand.name), brand]));

type HomeCommerceExperienceProps = {
  categories: KgmCategory[];
  featuredProducts: KgmProduct[];
  homepageBlocks?: HomepageBlock[];
};

export function HomeCommerceExperience({
  categories,
  featuredProducts,
  homepageBlocks = [],
}: HomeCommerceExperienceProps) {
  const visibleCategories = (categories.length ? categories : fallbackCategories).slice(0, 13);
  const bestSellers = featuredProducts.slice(0, 6);
  const newProducts = featuredProducts.slice(6, 12);
  const brandNames = Array.from(
    new Set(
      featuredProducts
        .map((product) => product.brand)
        .filter((brand): brand is string => Boolean(brand && brand.trim())),
    ),
  ).slice(0, 14);

  return (
    <main className="kgm-home-phase21 min-h-screen bg-[#fafafa] text-slate-950">
      <section className="mx-auto grid max-w-[1320px] gap-4 px-3 py-4 sm:px-4 lg:px-5 xl:grid-cols-[220px_minmax(0,1fr)]">
        <HomeLeftSidebar categories={visibleCategories} />

        <div className="min-w-0 space-y-3">
          <HeroSlider blocks={homepageBlocks.filter((block) => ["carousel_slide", "hero"].includes(block.type))} />
          <HomeCampaignShowcase />
          <HomeBrandBanner brands={brandNames} />
          <HomeVisualBanners />
        </div>
      </section>

      {bestSellers.length > 0 ? (
        <ProductSection title="Öne Çıkanlar" href="/products?sort=popular" products={bestSellers} priority />
      ) : null}

      {newProducts.length > 0 ? (
        <ProductSection title="Yeni Eklenen Ürünler" href="/products?sort=new" products={newProducts} />
      ) : null}
    </main>
  );
}

function HomeLeftSidebar({ categories }: { categories: KgmCategory[] }) {
  return (
    <aside className="kgm-home-left-sidebar hidden xl:block">
      <div className="mb-3 flex items-center justify-between px-1">
        <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-orange-600">
          Tüm Reyonlar
        </span>
        <Grid3X3 className="h-4 w-4 text-orange-500" />
      </div>
      <div className="grid gap-1">
        {categories.map((category) => {
          const Icon = categoryIcons[category.slug] ?? Grid3X3;
          return (
            <Link
              key={category.slug}
              href={`/kategori/${category.slug}`}
              className="kgm-home-sidebar-link group"
            >
              <span className="kgm-home-sidebar-icon">
                <Icon className="h-4 w-4" />
              </span>
              <span className="min-w-0 flex-1 truncate">{category.name}</span>
              <ChevronRight className="h-3.5 w-3.5 text-slate-300 transition group-hover:text-orange-500" />
            </Link>
          );
        })}
      </div>
      <Link
        href="/kategoriler"
        className="mt-3 flex items-center justify-between rounded-md border border-orange-200 bg-orange-50 px-3 py-2.5 text-xs font-semibold text-orange-700 transition hover:bg-orange-100"
      >
        Tüm Reyonları Gör
        <ArrowRight className="h-4 w-4" />
      </Link>
    </aside>
  );
}

type HeroSlide = {
  id: string;
  eyebrow: string;
  title: string;
  description: string;
  primary: string;
  secondary: string;
  href: string;
  secondaryHref: string;
  icon: typeof Store;
  stat: string;
  note: string;
  tone: string;
  image: string;
  visualOnly?: boolean;
};

function HeroSlider({ blocks }: { blocks: HomepageBlock[] }) {
  const fallbackSlides: HeroSlide[] = [
    {
      id: "fallback-main",
      eyebrow: "Karacabey Gross Market",
      title: "Günlük market alışverişinde ferah ve seçkin deneyim.",
      description: "Taze reyonlardan gross paketlere kadar ihtiyaçlarını sade, güven veren ve hızlı bir akışla tamamla.",
      primary: "Alışverişe Başla",
      secondary: "Tüm Reyonlar",
      href: "/products",
      secondaryHref: "/kategoriler",
      icon: Store,
      stat: "13",
      note: "13 ana reyon",
      tone: "orange",
      image: "/assets/kg-web.png",
    },
    {
      id: "fallback-fresh",
      eyebrow: "Taze Reyon",
      title: "Meyve sebze günlük seçkiyle sofraya hazır.",
      description: "Mutfak alışverişini özenle seçilmiş taze ürünlerden başlayarak kolayca planla.",
      primary: "Taze Ürünler",
      secondary: "Reyonlar",
      href: "/kategori/meyve-sebze",
      secondaryHref: "/kategoriler",
      icon: Leaf,
      stat: "Taze",
      note: "Taze seçki",
      tone: "green",
      image: "https://images.unsplash.com/photo-1542838132-92c53300491e?w=1600&q=85",
    },
    {
      id: "fallback-grocery",
      eyebrow: "Temel Gıda",
      title: "Ev stoklarını tek ekranda tamamla.",
      description: "Pirinç, yağ, bakliyat ve gross paketleri hızlıca sepete ekle.",
      primary: "Temel Gıda",
      secondary: "Ürünler",
      href: "/kategori/temel-gida",
      secondaryHref: "/products",
      icon: Package,
      stat: "Gross",
      note: "Avantajlı paket",
      tone: "slate",
      image: "https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=1600&q=85",
    },
    {
      id: "fallback-breakfast",
      eyebrow: "Kahvaltılık",
      title: "Sabah sofrası için pratik seçim.",
      description: "Süt ürünleri, peynir, zeytin ve kahvaltılık ürünleri tek akışta bul.",
      primary: "Kahvaltılık",
      secondary: "Ürünler",
      href: "/kategori/sut-kahvaltilik",
      secondaryHref: "/products",
      icon: Milk,
      stat: "Yeni",
      note: "Kahvaltı reyonu",
      tone: "cream",
      image: "https://images.unsplash.com/photo-1488477181946-6428a0291777?w=1600&q=85",
    },
    {
      id: "fallback-cleaning",
      eyebrow: "Temizlik",
      title: "Ev ihtiyaçlarında düzenli alışveriş.",
      description: "Deterjan, kağıt ürünleri ve bakım ihtiyaçlarını kolayca tamamla.",
      primary: "Temizlik",
      secondary: "Fırsatlar",
      href: "/kategori/temizlik",
      secondaryHref: "/kampanyalar",
      icon: Sparkles,
      stat: "Toplu",
      note: "Ev bakım",
      tone: "mint",
      image: "https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=1600&q=85",
    },
    {
      id: "fallback-snack",
      eyebrow: "Atıştırmalık",
      title: "Atıştırmalık ve çikolata hazır.",
      description: "Misafir, ofis ve ev keyfi için pratik ürünleri hızlıca seç.",
      primary: "Keşfet",
      secondary: "Sepete Git",
      href: "/kategori/atistirmalik",
      secondaryHref: "/sepet",
      icon: ShoppingBag,
      stat: "Hızlı",
      note: "Pratik sepet",
      tone: "purple",
      image: "https://images.unsplash.com/photo-1621939514649-280e2ee25f60?w=1600&q=85",
    },
    {
      id: "fallback-baby",
      eyebrow: "Bebek Reyonu",
      title: "Bebek ihtiyaçları düzenli listede.",
      description: "Bebek ürünlerini, bakım ihtiyaçlarını ve günlük stokları kolayca takip et.",
      primary: "Bebek",
      secondary: "Kategoriler",
      href: "/kategori/bebek",
      secondaryHref: "/kategoriler",
      icon: Baby,
      stat: "222",
      note: "Bebek reyonu",
      tone: "dark",
      image: "https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=1600&q=85",
    },
  ];
  const slides: HeroSlide[] = blocks.length > 0
    ? blocks.slice(0, 8).map((block, index) => ({
        id: String(block.id),
        eyebrow: "Karacabey Gross Market",
        title: block.title?.trim() ?? "",
        description: block.subtitle?.trim() ?? "",
        primary: block.link_label?.trim() ?? "",
        secondary: "Tüm Reyonlar",
        href: block.link_url ?? "/products",
        secondaryHref: "/kategoriler",
        icon: [Store, Tag, Sparkles, Package][index % 4],
        stat: "8K",
        note: "Admin galeri",
        tone: ["orange", "green", "slate", "mint"][index % 4],
        image: block.image_url ?? "/assets/kg-web.png",
        visualOnly: !block.title?.trim() && !block.subtitle?.trim(),
      }))
    : fallbackSlides;
  const [currentSlide, setCurrentSlide] = useState(0);
  const slide = slides[currentSlide];
  const Icon = slide.icon;

  useEffect(() => {
    const timer = window.setInterval(() => {
      setCurrentSlide((current) => (current + 1) % slides.length);
    }, 4500);

    return () => window.clearInterval(timer);
  }, [slides.length]);

  return (
    <section className={`kgm-home-visual-hero kgm-home-visual-hero--clean kgm-home-visual-hero--phase21 kgm-home-visual-hero--${slide.tone}${slide.visualOnly ? " kgm-home-visual-hero--image-only" : ""}`}>
      <img
        src={slide.image}
        alt={slide.title || "Karacabey Gross Market kampanyası"}
        className="kgm-home-hero-image"
      />
      {slide.visualOnly ? (
        <Link
          href={slide.href}
          className="kgm-home-visual-only-link"
          aria-label="Kampanyayı incele"
        />
      ) : (
        <>
          <div className="kgm-home-hero-copy">
            <span className="kgm-home-eyebrow">
              <Icon className="h-4 w-4" /> {slide.eyebrow}
            </span>
            {slide.title ? <h1>{slide.title}</h1> : null}
            {slide.description ? <p>{slide.description}</p> : null}
            <div className="flex flex-wrap gap-2.5">
              {slide.primary ? (
                <Link href={slide.href} className="kgm-home-hero-primary">
                  {slide.primary}
                  <ArrowRight className="h-4 w-4" />
                </Link>
              ) : null}
              <Link href={slide.secondaryHref} className="kgm-home-hero-secondary">
                {slide.secondary}
              </Link>
            </div>
          </div>

          <div className="kgm-home-hero-art" aria-hidden="true">
            <div className="kgm-home-hero-box kgm-home-hero-box--large">
              <Icon className="h-14 w-14" />
            </div>
            <div className="kgm-home-hero-box kgm-home-hero-box--small">
              <ShoppingBag className="h-6 w-6" />
              <span>{slide.stat}</span>
            </div>
          </div>

          <div className="kgm-home-slider-note" aria-hidden="true">
            {slide.note}
          </div>
        </>
      )}

      <div className="kgm-home-slider-dots" aria-label="Slider sayfaları">
        {slides.map((item, index) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setCurrentSlide(index)}
            className={index === currentSlide ? "is-active" : undefined}
            aria-label={`Slayt ${index + 1}`}
          />
        ))}
      </div>
    </section>
  );
}

function HomeCampaignShowcase() {
  const cards = [
    {
      title: "Yeni üyelere %15 hoş geldin indirimi",
      text: "İlk alışverişini avantajlı fiyatlarla tamamla.",
      href: "/kampanyalar/hosgeldin-indirimi",
      cta: "Hoş Geldin Kampanyası",
      image: "/campaigns/hosgeldin-indirimi.webp",
      tone: "orange",
      icon: Tag,
    },
    {
      title: "Taze reyon seçkisi",
      text: "Meyve, sebze ve kahvaltılık alışverişini tek akışta tamamla.",
      href: "/kategori/meyve-sebze",
      cta: "Taze Reyona Git",
      image: "https://images.unsplash.com/photo-1488459716781-6815cecdf030?w=900&q=85",
      tone: "green",
      icon: Leaf,
    },
    {
      title: "Ev ve temizlik paketleri",
      text: "Deterjan, kağıt ürünleri ve bakım ihtiyaçlarında düzenli alışveriş.",
      href: "/kategori/temizlik",
      cta: "Ürünleri İncele",
      image: "https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=900&q=85",
      tone: "orange",
      icon: Sparkles,
    },
  ];

  return (
    <section className="kgm-home-campaign-showcase" aria-label="Kampanya alanları">
      {cards.map((item) => {
        const Icon = item.icon;
        return (
          <Link
            key={item.title}
            href={item.href}
            className={`kgm-home-campaign-card kgm-home-campaign-card--${item.tone}`}
          >
            <img src={item.image} alt="" aria-hidden="true" />
            <div>
              <span className="kgm-home-campaign-badge">
                <Icon className="h-4 w-4" />
                Kampanya
              </span>
              <h2>{item.title}</h2>
              <p>{item.text}</p>
              <strong>
                {item.cta}
                <ArrowRight className="h-4 w-4" />
              </strong>
            </div>
          </Link>
        );
      })}
    </section>
  );
}

function HomeBrandBanner({ brands }: { brands: string[] }) {
  const items = brands
    .map((brand) => brandLogoByName.get(normalizeBrandKey(brand)))
    .filter((brand): brand is BrandLogo => Boolean(brand));
  const logoItems = items.length >= 6 ? items : brandLogoCatalog;
  const marqueeItems = [...logoItems, ...logoItems, ...logoItems];

  return (
    <section className="kgm-home-brand-banner" aria-label="Markalar">
      <div className="kgm-home-brand-banner__head">
        <span>Markalar</span>
        <strong>Sevilen ürünler tek vitrinde</strong>
      </div>
      <div className="kgm-home-brand-track">
        {marqueeItems.map((brand, index) => (
          <Link
            key={`${brand.name}-${index}`}
            href={`/products?q=${encodeURIComponent(brand.name)}`}
            className="kgm-home-brand-chip"
            aria-label={`${brand.name} ürünleri`}
          >
            <BrandLogoImage brand={brand} />
          </Link>
        ))}
      </div>
    </section>
  );
}

function BrandLogoImage({ brand }: { brand: BrandLogo }) {
  const [failed, setFailed] = useState(false);

  if (failed) {
    return <span>{brand.name}</span>;
  }

  return (
    <img
      src={brand.src}
      alt={brand.name}
      loading="lazy"
      decoding="async"
      onError={() => setFailed(true)}
    />
  );
}

function HomeVisualBanners() {
  const banners = [
    {
      title: "Toplu alışverişte kurumsal avantaj",
      text: "İşletme, ofis ve aile alışverişlerinde gross paketleri hızlıca planla.",
      href: "/kurumsal-siparis",
      image: "https://images.unsplash.com/photo-1534723452862-4c874018d66d?w=1100&q=85",
      icon: Package,
    },
    {
      title: "Karacabey teslimat akışı",
      text: "Siparişini hazırla, adresine göre teslimat seçeneklerini gör.",
      href: "/teslimat-bolgeleri",
      image: "https://images.unsplash.com/photo-1566576721346-d4a3b4eaeb55?w=1100&q=85",
      icon: Truck,
    },
  ];

  return (
    <section className="kgm-home-visual-banners" aria-label="Alışveriş bannerları">
      {banners.map((card) => {
        const Icon = card.icon;
        return (
          <Link key={card.title} href={card.href} className="kgm-home-visual-banner">
            <img src={card.image} alt="" aria-hidden="true" />
            <div>
              <span>
                <Icon className="h-4 w-4" />
                Öne çıkan
              </span>
              <h2>{card.title}</h2>
              <p>{card.text}</p>
            </div>
            <ArrowRight className="kgm-home-visual-banner__arrow h-5 w-5" />
          </Link>
        );
      })}
    </section>
  );
}

function ProductSection({
  title,
  href,
  products,
  priority = false,
}: {
  title: string;
  href: string;
  products: KgmProduct[];
  priority?: boolean;
}) {
  return (
    <section className="mx-auto max-w-[1320px] px-3 py-3 sm:px-4 lg:px-5">
      <div className="kgm-home-section-card kgm-home-section-card--compact">
        <div className="mb-3 flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold tracking-tight text-slate-950 md:text-xl">
            {title}
          </h2>
          <Link href={href} className="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-700 md:text-sm">
            Tümünü Gör
            <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
          {products.map((product, index) => (
            <ProductCard key={product.slug} product={product} priority={priority && index < 2} />
          ))}
        </div>
      </div>
    </section>
  );
}
