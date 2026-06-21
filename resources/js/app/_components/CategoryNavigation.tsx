import Link from "next/link";
import {
  ArrowRight,
  Grid3X3,
  Package,
  Search,
  ShoppingBag,
  Sparkles,
  Tag,
  Truck,
  type LucideIcon,
} from "lucide-react";
import type { KgmCategory } from "@/lib/catalog";

type CategoryNavigationProps = {
  categories?: KgmCategory[];
};

const defaultCategories: KgmCategory[] = [
  { name: "Temel Gıda", slug: "temel-gida", count: 1250 },
  { name: "Meyve & Sebze", slug: "meyve-sebze", count: 480 },
  { name: "Et & Tavuk", slug: "et-tavuk", count: 210 },
  { name: "Süt & Kahvaltı", slug: "sut-kahvalti", count: 820 },
  { name: "Atıştırmalık", slug: "atistirmali", count: 1560 },
  { name: "İçecek", slug: "icecek", count: 730 },
  { name: "Temizlik", slug: "temizlik", count: 940 },
  { name: "Kozmetik", slug: "kozmetik-bakim", count: 650 },
];

export function CategoryNavigation({ categories }: CategoryNavigationProps) {
  const displayCategories = categories?.length ? categories.slice(0, 8) : defaultCategories;

  return (
    <section className="kgm-category-v17 mx-auto max-w-[1280px] px-4 py-6 md:px-6">
      <div className="mb-4 flex items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-slate-950">Reyonlar</h2>
        </div>
        <Link
          href="/kategoriler"
          className="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-orange-200 hover:text-orange-600"
        >
          Tümü
          <ArrowRight size={14} />
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
        {displayCategories.map((category) => {
          const Icon = resolveCategoryIcon(category.slug, category.name);
          const href = `/kategori/${category.slug}`;

          return (
            <Link
              key={category.slug}
              href={href}
              className="group flex min-h-[92px] flex-col justify-between rounded-md border border-slate-200 bg-white p-3 text-left hover:border-orange-200 hover:bg-orange-50/40"
            >
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-slate-50 text-orange-600 ring-1 ring-slate-200 group-hover:bg-white">
                <Icon size={18} />
              </span>
              <span>
                <strong className="line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                  {category.name}
                </strong>
                {category.count ? (
                  <small className="mt-0.5 block text-xs text-slate-500">
                    {category.count} ürün
                  </small>
                ) : null}
              </span>
            </Link>
          );
        })}

        <Link
          href="/products"
          className="group flex min-h-[92px] flex-col justify-between rounded-md border border-orange-200 bg-orange-600 p-3 text-left text-white hover:bg-orange-700"
        >
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-white/15 ring-1 ring-white/20">
            <Grid3X3 size={18} />
          </span>
          <span>
            <strong className="block text-sm font-semibold leading-5">Tüm Ürünler</strong>
            <small className="mt-0.5 block text-xs text-orange-50">Kataloğu aç</small>
          </span>
        </Link>
      </div>
    </section>
  );
}

function resolveCategoryIcon(slug: string, name: string): LucideIcon {
  const key = `${slug} ${name}`.toLocaleLowerCase("tr-TR");

  if (/kampanya|fırsat|indirim/.test(key)) return Tag;
  if (/temizlik|bakım|kozmetik/.test(key)) return Sparkles;
  if (/kargo|teslimat/.test(key)) return Truck;
  if (/arama|barkod/.test(key)) return Search;
  if (/gıda|market|ürün|meyve|sebze|süt|et|içecek|atıştır/.test(key)) return ShoppingBag;

  return Package;
}
