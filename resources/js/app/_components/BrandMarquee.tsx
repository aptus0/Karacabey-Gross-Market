import React from "react";
import Image from "next/image";

// A list of requested brands
const BRANDS = [
  "Ülker", "Eti", "Torku", "Pınar", "Sütaş", "İçim", "Danone", "Sek", 
  "Dimes", "Tamek", "Tat", "Penguen", "Bizim", "Komili", "Yudum", "Orkide", 
  "Filiz", "Barilla", "Nuh’un Ankara", "Duru", "Yayla", "Reis", "Söke", "Sinangil", 
  "SuperFresh", "Banvit", "Beypiliç", "Namet", "Pınar Et", "Şölen", "Kent", "Tadelle", 
  "Nestlé", "Çaykur", "Doğuş", "Lipton", "Kahve Dünyası", "Nescafé", "Jacobs", 
  "Coca-Cola", "Pepsi", "Fanta", "Sprite", "Cappy", "Uludağ", "Erikli", "Hayat", "Sırma", "Pürsu"
];

export function BrandMarquee() {
  // We duplicate the list to ensure seamless scrolling
  const marqueeItems = [...BRANDS, ...BRANDS, ...BRANDS];

  return (
    <section className="py-12 overflow-hidden bg-white/40 backdrop-blur-md border-y border-white/50 relative">
      <div className="max-w-[1120px] mx-auto px-6 mb-6">
        <h2 className="text-xl font-bold text-gray-900">Güvenilir Markalar</h2>
        <p className="text-sm text-gray-500">Dünyanın ve Türkiye&apos;nin en popüler markaları tek çatı altında.</p>
      </div>

      <div className="relative flex w-[200%] md:w-[300%] overflow-hidden">
        {/* Transparent gradient masks for smooth fade in/out on edges */}
        <div className="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-[rgba(248,250,252,1)] to-transparent z-10 pointer-events-none" />
        <div className="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-[rgba(248,250,252,1)] to-transparent z-10 pointer-events-none" />

        <div className="flex animate-marquee gap-8 md:gap-12 px-4 whitespace-nowrap">
          {marqueeItems.map((brand, index) => (
            <div 
              key={`brand-${index}`} 
              className="flex items-center justify-center min-w-[120px] h-[60px] px-6 rounded-xl bg-white/80 border border-white/90 shadow-sm transition-transform hover:scale-105 cursor-default glass-card"
            >
              <span className="font-extrabold text-gray-700 tracking-tight text-lg">
                {brand}
              </span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
