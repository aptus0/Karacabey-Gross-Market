"use client";

import React from "react";
import Link from "next/link";
import {
  BadgePercent,
  ChevronLeft,
  ChevronRight,
  Clock3,
  PackageCheck,
  ShieldCheck,
  Sparkles,
  Truck,
} from "lucide-react";

const slides = [
  {
    eyebrow: "Turuncu Gross Fırsatları",
    title: "Market alışverişinde hızlı, taze ve ekonomik dönem.",
    subtitle:
      "12K+ üründe hızlı arama, güvenli ödeme ve Karacabey içi planlı teslimat.",
    cta: "Alışverişe Başla",
    secondary: "Haftanın Kampanyaları",
    href: "/products",
    secondaryHref: "/kampanyalar",
    image: "https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&q=80",
    badge: "Bugün sepette turuncu fırsat",
    stat: "%25'e varan indirim",
    tone: "orange",
  },
  {
    eyebrow: "Web ve Mobil Uyumlu",
    title: "Mobilde başla, web’de rahatça tamamla.",
    subtitle:
      "Mobil ve web alışveriş akışı aynı hesap deneyimiyle düzenli çalışır.",
    cta: "Ürünleri Keşfet",
    secondary: "Nasıl Çalışır?",
    href: "/products",
    secondaryHref: "/kurumsal/teslimat-bolgeleri",
    image: "https://images.unsplash.com/photo-1550989460-0adf9ea622e2?w=1200&q=80",
    badge: "Web + Mobil aynı sepet",
    stat: "Anlık güncel sepet",
    tone: "dark",
  },
  {
    eyebrow: "Zengin Ürün Seçkisi",
    title: "Ürün, fiyat ve kampanyalar düzenli yenilenir.",
    subtitle:
      "Yeni ürünler ve kampanyalar sade bir alışveriş akışıyla sunulur.",
    cta: "Kategorilere Git",
    secondary: "Kampanyaları Gör",
    href: "/products",
    secondaryHref: "/kampanyalar",
    image: "https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=1200&q=80",
    badge: "12K+ ürün hazır",
    stat: "Hızlı alışveriş",
    tone: "cream",
  },
];

const assuranceItems = [
  { icon: Truck, label: "Aynı gün teslimat" },
  { icon: ShieldCheck, label: "PayTR + 3D Secure" },
  { icon: Clock3, label: "Güncel ürün" },
];

const floatingProducts = [
  { name: "Taze Reyon", note: "Günlük ürün" },
  { name: "Kahvaltılık", note: "Seçili ürünler" },
  { name: "Gross Paket", note: "Avantajlı" },
];

export function HeroSlider() {
  const [currentSlide, setCurrentSlide] = React.useState(0);
  const slide = slides[currentSlide];

  const handlePrev = () => {
    setCurrentSlide((prev) => (prev - 1 + slides.length) % slides.length);
  };

  const handleNext = () => {
    setCurrentSlide((prev) => (prev + 1) % slides.length);
  };

  return (
    <section className="kgm-home-hero kgm-home-hero--phase6 relative mx-auto max-w-[1440px] px-4 py-6 md:px-10 md:py-8">
      <div className={`kgm-hero-shell kgm-hero-shell--${slide.tone} relative overflow-hidden rounded-[38px] border border-orange-100 bg-[#fff7ed] shadow-2xl shadow-orange-950/8`}>
        <div className="kgm-hero-noise" />
        <div className="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-orange-300/30 blur-3xl" />
        <div className="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-amber-200/35 blur-3xl" />
        <div className="absolute left-[48%] top-12 hidden h-40 w-40 rounded-full border border-white/50 lg:block" />
        <div className="absolute left-[55%] top-28 hidden h-72 w-72 rounded-full border border-orange-200/45 lg:block" />

        <div className="grid min-h-[560px] grid-cols-1 lg:grid-cols-[1.02fr_0.98fr]">
          <div className="relative z-10 flex flex-col justify-center p-7 md:p-12 xl:p-16">
            <div className="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-orange-200 bg-white/85 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700 shadow-sm backdrop-blur-sm">
              <Sparkles size={14} />
              {slide.eyebrow}
            </div>

            <h1 className="max-w-2xl text-4xl font-black leading-[0.96] tracking-[-0.06em] text-slate-950 md:text-6xl xl:text-[76px]">
              {slide.title}
            </h1>
            <p className="mt-6 max-w-xl text-base font-semibold leading-8 text-slate-600 md:text-lg">
              {slide.subtitle}
            </p>

            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href={slide.href}
                className="inline-flex h-14 items-center gap-3 rounded-2xl bg-gradient-to-r from-[#ff7a00] to-[#ff9a1f] px-7 text-sm font-black text-white shadow-lg shadow-orange-500/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-orange-500/30"
              >
                {slide.cta}
                <ChevronRight size={18} />
              </Link>
              <Link
                href={slide.secondaryHref}
                className="inline-flex h-14 items-center rounded-2xl border border-orange-200 bg-white px-7 text-sm font-black text-slate-900 shadow-sm transition hover:border-orange-300 hover:bg-orange-50"
              >
                {slide.secondary}
              </Link>
            </div>

            <div className="mt-10 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
              {assuranceItems.map((item) => {
                const Icon = item.icon;
                return (
                  <div key={item.label} className="kgm-hero-assurance flex items-center gap-3 rounded-2xl border border-orange-100 bg-white/75 p-3 shadow-sm backdrop-blur-sm">
                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100">
                      <Icon size={18} />
                    </span>
                    <span className="text-xs font-black leading-4 text-slate-700">{item.label}</span>
                  </div>
                );
              })}
            </div>

            <div className="mt-9 flex items-center gap-3">
              <div className="flex gap-2">
                {slides.map((_, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => setCurrentSlide(idx)}
                    aria-label={`Slayt ${idx + 1}`}
                    className={`h-2.5 rounded-full transition-all duration-300 ${
                      idx === currentSlide ? "w-12 bg-orange-500" : "w-2.5 bg-orange-200"
                    }`}
                  />
                ))}
              </div>
              <span className="hidden text-xs font-black uppercase tracking-[0.14em] text-orange-700 sm:inline">
                {String(currentSlide + 1).padStart(2, "0")} / {String(slides.length).padStart(2, "0")}
              </span>
            </div>
          </div>

          <div className="relative hidden min-h-[560px] lg:block">
            <div className="absolute inset-0 z-10 bg-gradient-to-r from-[#fff7ed] via-[#fff7ed]/55 to-transparent" />
            <img
              src={slide.image}
              alt="Karacabey Gross Market kampanya görseli"
              className="h-full w-full object-cover"
            />
            <div className="absolute inset-x-10 bottom-10 z-20 grid grid-cols-[1fr_0.82fr] gap-4">
              <div className="rounded-[2rem] bg-white/92 p-6 shadow-2xl shadow-slate-950/15 backdrop-blur-md ring-1 ring-white/70">
                <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-orange-700">
                  <BadgePercent size={14} />
                  {slide.badge}
                </div>
                <p className="text-3xl font-black leading-tight text-slate-950">{slide.stat}</p>
                <small className="mt-2 block text-xs font-bold text-slate-500">Fiyat ve stok bilgileri sipariş öncesi kontrol edilir.</small>
              </div>

              <div className="grid gap-3">
                {floatingProducts.map((item) => (
                  <div key={item.name} className="flex items-center gap-3 rounded-2xl bg-white/88 p-3 shadow-xl shadow-slate-950/10 backdrop-blur-md ring-1 ring-white/70">
                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 ring-1 ring-orange-100"><PackageCheck size={20} /></span>
                    <span className="min-w-0">
                      <strong className="block truncate text-sm font-black text-slate-950">{item.name}</strong>
                      <small className="block text-xs font-bold text-slate-500">{item.note}</small>
                    </span>
                    <PackageCheck size={18} className="ml-auto text-orange-600" />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          onClick={handlePrev}
          aria-label="Önceki slayt"
          className="absolute left-5 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-900 shadow-lg transition hover:bg-orange-50 md:flex"
        >
          <ChevronLeft size={22} />
        </button>
        <button
          type="button"
          onClick={handleNext}
          aria-label="Sonraki slayt"
          className="absolute right-5 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-900 shadow-lg transition hover:bg-orange-50 md:flex"
        >
          <ChevronRight size={22} />
        </button>
      </div>
    </section>
  );
}
