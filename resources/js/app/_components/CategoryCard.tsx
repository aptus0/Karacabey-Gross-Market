import Link from "next/link";
import {
  Apple,
  Beef,
  Candy,
  Coffee,
  Cookie,
  Droplets,
  Egg,
  Fish,
  Flower2,
  Milk,
  Package,
  ShoppingBag,
  Slice,
  Sparkles,
  Utensils,
  Wheat,
  Wine,
  type LucideIcon,
} from "lucide-react";
import type { KgmCategory } from "@/lib/catalog";
import { createElement } from "react";

type CategoryCardProps = {
  category: KgmCategory;
};

export function CategoryCard({ category }: CategoryCardProps) {
  const Icon = resolveIcon(category.slug, category.name);
  const colorClass = resolveColor(category.slug, category.name);

  return (
    <Link className={`category-card ${colorClass}`} href={`/kategori/${category.slug}`}>
      <span className="category-card__icon" aria-hidden="true">
        {createElement(Icon, { size: 20 })}
      </span>
      <span className="category-card__name">{category.name}</span>
      {category.count ? (
        <strong className="category-card__count">{category.count} ürün</strong>
      ) : null}
    </Link>
  );
}

/** Kategori adı veya slug'a göre ikon seç */
function resolveIcon(slug: string, name: string): LucideIcon {
  const key = `${slug} ${name}`.toLowerCase();

  if (/çips|cips|atıştırmalık|snack/.test(key)) return Cookie;
  if (/tatlı|şeker|bisküvi|çikolata|gofret/.test(key)) return Candy;
  if (/un|makarna|pirinç|bulgur|tahıl/.test(key)) return Wheat;
  if (/kuru yemiş|kuruyemiş|fındık|badem|ceviz/.test(key)) return Sparkles;
  if (/meyve suyu|içecek|su|meşrubat|drinks/.test(key)) return Droplets;
  if (/tuz|baharat|çeşni|bar/.test(key)) return Utensils;
  if (/peynir|süt|yoğurt|kahvaltı/.test(key)) return Milk;
  if (/fırın|ekmek|pide/.test(key)) return Wheat;
  if (/meyve|sebze|yeşil/.test(key)) return Apple;
  if (/et|tavuk|kıyma|işlenmiş/.test(key)) return Beef;
  if (/balık|deniz ürünü/.test(key)) return Fish;
  if (/yumurta/.test(key)) return Egg;
  if (/temizlik|deterjan|sabun|hijyen/.test(key)) return Sparkles;
  if (/kişisel|kozmetik|diş|bakım/.test(key)) return Flower2;
  if (/kahve|çay|nescafé/.test(key)) return Coffee;
  if (/dondurma|donmuş/.test(key)) return Slice;
  if (/şarap|alkol|bira/.test(key)) return Wine;
  if (/bebek/.test(key)) return ShoppingBag;

  return Package;
}

/** Kategori adı veya slug'a göre renk teması seç */
function resolveColor(slug: string, name: string): string {
  const key = `${slug} ${name}`.toLowerCase();

  if (/çips|cips|tatlı|şeker|bisküvi|çikolata/.test(key)) return "category-card--amber";
  if (/meyve|sebze|yeşil/.test(key)) return "category-card--green";
  if (/süt|peynir|yoğurt|kahvaltı/.test(key)) return "category-card--blue";
  if (/et|tavuk|balık/.test(key)) return "category-card--red";
  if (/içecek|meyve suyu|su/.test(key)) return "category-card--cyan";
  if (/temizlik|deterjan/.test(key)) return "category-card--teal";
  if (/kişisel|kozmetik|bakım/.test(key)) return "category-card--pink";
  if (/kahve|çay/.test(key)) return "category-card--brown";

  return "category-card--orange";
}
