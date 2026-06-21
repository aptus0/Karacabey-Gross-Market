import React from "react";
import { ProductCard } from "./ProductCard";
import type { KgmProduct } from "@/lib/catalog";
import { ChevronRight } from "lucide-react";
import Link from "next/link";

type ShowroomSectionProps = {
  title: string;
  subtitle: string;
  products: KgmProduct[];
  theme?: "default" | "cosmetics" | "bestseller";
  actionLink?: string;
  actionText?: string;
};

export function ShowroomSection({
  title,
  subtitle,
  products,
  theme = "default",
  actionLink,
  actionText = "Tümünü Gör",
}: ShowroomSectionProps) {
  if (!products || products.length === 0) return null;

  // Farklı konseptlere göre renk paletleri ve efektler
  const themeClasses = {
    default: "bg-gradient-to-br from-[#fffaf5] via-white to-[#fff1df] border-orange-100",
    cosmetics: "bg-gradient-to-br from-[#fff0f5] to-[#ffe4e1] border-[#ffb6c1]/30", // Soft pembe
    bestseller: "bg-gradient-to-br from-[#fffbeb] to-[#fef3c7] border-[#fde68a]/40", // Soft altın/sarı
  };

  return (
    <section className={`py-10 md:py-14 px-6 md:px-10 my-10 md:my-12 rounded-3xl relative overflow-hidden glass-panel ${themeClasses[theme]}`}>
      {/* Dekoratif arkaplan bulanıklıkları */}
      <div className="absolute top-0 right-0 w-96 h-96 bg-white/30 blur-3xl rounded-full -translate-y-1/2 translate-x-1/3 pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-96 h-96 bg-white/30 blur-3xl rounded-full translate-y-1/2 -translate-x-1/3 pointer-events-none" />

      <div className="relative z-10">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
          <div>
            <h2 className="showroom-title text-2xl md:text-3xl font-bold mb-2">{title}</h2>
            <p className="showroom-subtitle text-gray-600">{subtitle}</p>
          </div>

          {actionLink && (
            <Link
              href={actionLink}
              className="inline-flex items-center gap-2 rounded-2xl border border-orange-100 bg-white px-5 py-3 text-sm font-black text-orange-700 shadow-sm backdrop-blur-md transition hover:bg-orange-50 hover:shadow-md"
            >
              {actionText} <ChevronRight size={18} />
            </Link>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {products.slice(0, 5).map((product) => (
            <div key={product.slug} className="transform transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
              <ProductCard product={product} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
