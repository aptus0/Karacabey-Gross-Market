import Link from "next/link";
import { ArrowRight, Percent, Truck, Zap } from "lucide-react";

type BannerItem = {
  eyebrow: string;
  title: string;
  description: string;
  href: string;
  cta: string;
  variant: "orange" | "green" | "navy";
  Icon: typeof Percent;
};

const banners: BannerItem[] = [
  {
    eyebrow: "Fırsat",
    title: "Haftalık Gross Fırsatları",
    description: "Temel gıda ve günlük ürünlerde bu haftaya özel avantajlı sepetler.",
    href: "/products",
    cta: "Fırsatları Gör",
    variant: "orange",
    Icon: Percent,
  },
  {
    eyebrow: "Hız",
    title: "Aynı Gün Teslimat",
    description: "Karacabey içinde siparişleriniz aynı gün kapınıza ulaşır.",
    href: "/cargo-tracking",
    cta: "Teslimat Bilgisi",
    variant: "green",
    Icon: Truck,
  },
  {
    eyebrow: "Avantaj",
    title: "Gross Sepet İndirimi",
    description: "Toplu alışverişlerde ekstra indirim fırsatlarını kaçırma.",
    href: "/products",
    cta: "Alışverişe Başla",
    variant: "navy",
    Icon: Zap,
  },
];

export function AdvertisingBanners() {
  return (
    <section className="content-band" aria-label="Kampanyalar">
      <div className="ad-banners">
        {banners.map((banner) => (
          <Link
            key={banner.title}
            href={banner.href}
            className={`ad-banner-card ad-banner-card--${banner.variant}`}
          >
            <div className="ad-banner-card__icon">
              <banner.Icon size={28} />
            </div>

            <div className="ad-banner-card__body">
              <p className="eyebrow">{banner.eyebrow}</p>
              <h3>{banner.title}</h3>
              <p>{banner.description}</p>
            </div>

            <div className="ad-banner-card__cta">
              <span>{banner.cta}</span>
              <ArrowRight size={16} />
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
