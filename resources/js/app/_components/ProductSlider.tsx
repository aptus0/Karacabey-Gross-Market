"use client";

import { ProductCard } from "@/app/_components/ProductCard";
import type { KgmProduct } from "@/lib/catalog";
import { cn } from "@/lib/utils";
import { Autoplay, FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

type ProductSliderProps = {
  products: KgmProduct[];
  ariaLabel?: string;
  autoplay?: boolean;
};

export function ProductSlider({ products, ariaLabel = "Öne çıkan ürünler", autoplay = false }: ProductSliderProps) {
  if (products.length === 0) {
    return null;
  }

  return (
    <Swiper
      aria-label={ariaLabel}
      className={cn("product-slider", autoplay && "product-slider--autoplay")}
      modules={[Autoplay, FreeMode, Navigation]}
      slidesPerView={1.18}
      spaceBetween={12}
      speed={650}
      freeMode={{ enabled: true, momentumRatio: 0.7 }}
      navigation={products.length > 4}
      loop={products.length > 5}
      autoplay={
        autoplay && products.length > 1
          ? {
              delay: 2600,
              disableOnInteraction: false,
              pauseOnMouseEnter: true,
            }
          : false
      }
      breakpoints={{
        420: { slidesPerView: 1.55, spaceBetween: 12 },
        560: { slidesPerView: 2.15, spaceBetween: 14 },
        820: { slidesPerView: 3.05, spaceBetween: 16 },
        1120: { slidesPerView: 4.05, spaceBetween: 18 },
        1480: { slidesPerView: 5.05, spaceBetween: 18 },
      }}
    >
      {products.map((product, index) => (
        <SwiperSlide key={`${product.id ?? product.slug}-${product.slug}`}>
          <div className="product-slider__item">
            <ProductCard product={product} priority={index < 3} />
          </div>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
