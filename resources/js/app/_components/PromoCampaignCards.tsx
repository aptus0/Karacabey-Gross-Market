"use client";

import React from "react";
import Link from "next/link";
import { ChevronRight, Clock3, PackageCheck, Sparkles } from "lucide-react";

interface PromoCard {
  title: string;
  subtitle: string;
  metric: string;
  href: string;
  buttonText: string;
  image: string;
  icon: "spark" | "clock" | "package";
  variant: "orange" | "cream" | "dark";
}

const campaignCards: PromoCard[] = [
  {
    title: "Meyve & sebzede turuncu tazelik",
    subtitle: "Günlük seçilen ürünlerde avantajlı fiyat ve hızlı teslimat.",
    metric: "%20'ye varan indirim",
    image: "https://images.unsplash.com/photo-1488459716781-6815cecdf030?w=720&h=560&fit=crop&q=80",
    href: "/kategori/meyve-sebze",
    buttonText: "Taze Ürünleri Gör",
    icon: "spark",
    variant: "orange",
  },
  {
    title: "Haftanın sepet paketleri",
    subtitle: "Aile alışverişi ve gross paketlerde seçili ürün kampanyaları.",
    metric: "Sepette ekstra fırsat",
    image: "https://images.unsplash.com/photo-1542838132-92c53300491e?w=720&h=560&fit=crop&q=80",
    href: "/products?q=fırsat",
    buttonText: "Fırsatları İncele",
    icon: "package",
    variant: "cream",
  },
  {
    title: "Toplu alışveriş ve işletme desteği",
    subtitle: "İş yeri, ofis ve toplu alımlar için hızlı teklif ve takip akışı.",
    metric: "Kurumsal avantaj",
    image: "https://images.unsplash.com/photo-1534723452862-4c874018d66d?w=720&h=560&fit=crop&q=80",
    href: "/kurumsal/teslimat-bolgeleri",
    buttonText: "Teklif Akışını Aç",
    icon: "clock",
    variant: "dark",
  },
];

const iconMap = {
  spark: Sparkles,
  clock: Clock3,
  package: PackageCheck,
};

export function PromoCampaignCards() {
  return (
    <section className="kgm-promo-section mx-auto max-w-[1280px] px-4 py-10 md:px-6">
      <div className="mb-5 flex items-end justify-between gap-4">
        <div>
          <p className="text-xs font-black uppercase tracking-[0.22em] text-orange-600">Kampanyalar</p>
          <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950 md:text-3xl">Turuncu fırsat alanları</h2>
        </div>
        <Link href="/kampanyalar" className="hidden items-center gap-2 rounded-2xl border border-orange-100 bg-white px-4 py-2.5 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50 sm:inline-flex">
          Tüm Kampanyalar
          <ChevronRight size={16} />
        </Link>
      </div>

      <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
        {campaignCards.map((card, index) => {
          const Icon = iconMap[card.icon];
          return (
            <Link
              key={card.title}
              href={card.href}
              className={`kgm-promo-card kgm-promo-card--${card.variant} group relative overflow-hidden rounded-[2rem] border p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-2xl`}
              style={{ animationDelay: `${index * 70}ms` }}
            >
              <div className="absolute -right-10 -top-12 h-32 w-32 rounded-full bg-white/22 blur-2xl" />
              <div className="relative z-10 flex min-h-[360px] flex-col justify-between gap-5">
                <div>
                  <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/82 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.14em] text-orange-700 shadow-sm ring-1 ring-white/70">
                    <Icon size={14} />
                    {card.metric}
                  </div>
                  <h3 className="max-w-[12rem] text-3xl font-black leading-[1.02] tracking-[-0.045em]">
                    {card.title}
                  </h3>
                  <p className="mt-3 max-w-[14rem] text-sm font-semibold leading-6 opacity-85">{card.subtitle}</p>
                </div>

                <span className="inline-flex w-fit items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-black text-slate-950 shadow-lg shadow-slate-950/8 transition group-hover:gap-3">
                  {card.buttonText}
                  <ChevronRight size={17} />
                </span>
              </div>

              <div className="absolute bottom-4 right-4 h-36 w-36 overflow-hidden rounded-[1.7rem] shadow-xl shadow-slate-950/12 ring-1 ring-white/55 md:h-40 md:w-40">
                <img src={card.image} alt={card.title} className="h-full w-full object-cover transition duration-500 group-hover:scale-110" />
              </div>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
