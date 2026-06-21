"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { fallbackSlides, fetchHomepageBlocks } from "@/lib/homepage";

export function HomeCarousel() {
  const [activeIndex, setActiveIndex] = useState(0);
  const { data } = useQuery({
    queryKey: ["homepage", "carousel"],
    queryFn: ({ signal }) => fetchHomepageBlocks(signal),
  });
  const slides = data?.filter((block) => block.type === "carousel_slide") ?? fallbackSlides;
  const hasMultipleSlides = slides.length > 1;

  return (
    <section className="home-carousel home-carousel--swiper" aria-label="Ana sayfa slider">
      <Swiper
        autoplay={
          hasMultipleSlides
            ? {
                delay: 4200,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
              }
            : false
        }
        className="home-carousel__swiper"
        loop={hasMultipleSlides}
        modules={[Autoplay, Navigation, Pagination]}
        navigation={hasMultipleSlides}
        pagination={{ clickable: true, dynamicBullets: true }}
        speed={900}
        onSlideChange={(swiper) => setActiveIndex(swiper.realIndex)}
      >
        {slides.map((slide) => {
          const safeImage = safeMediaUrl(slide.image_url);

          return (
            <SwiperSlide key={slide.id}>
              <div className="home-carousel__slide">
                <div className="home-carousel__shell">
                  <div className="home-carousel__media" aria-hidden="true">
                    {safeImage ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={safeImage} alt="" />
                    ) : null}
                  </div>
                  <div className="home-carousel__content">
                    <div className="home-carousel__meta">
                      <span>Yeni nesil market deneyimi</span>
                      <span>
                        {(activeIndex + 1).toString().padStart(2, "0")} / {slides.length.toString().padStart(2, "0")}
                      </span>
                    </div>
                    <p className="eyebrow">Karacabey Gross Market</p>
                    <h1>{slide.title}</h1>
                    {slide.subtitle ? <p>{slide.subtitle}</p> : null}
                    <div className="home-carousel__highlights" aria-label="Öne çıkan özellikler">
                      <span>Yerel stok görünürlüğü</span>
                      <span>Güvenli ödeme akışı</span>
                      <span>Hızlı teslimat planı</span>
                    </div>
                    <div className="hero-section__actions">
                      {slide.link_url ? (
                        <Link className="primary-action" href={slide.link_url}>
                          {slide.link_label || "İncele"}
                        </Link>
                      ) : null}
                      <Link className="secondary-action" href="/products">
                        Tüm Ürünler
                      </Link>
                    </div>
                    <div className="home-carousel__status" aria-hidden="true">
                      <div className="home-carousel__progress">
                        <span
                          key={`progress-${activeIndex}`}
                          className={hasMultipleSlides ? "is-animated" : undefined}
                          style={{ transform: `scaleX(${hasMultipleSlides ? 0 : 1})` }}
                        />
                      </div>
                      <small>Slider otomatik olarak akıyor</small>
                    </div>
                  </div>
                </div>
              </div>
            </SwiperSlide>
          );
        })}
      </Swiper>
    </section>
  );
}

function safeMediaUrl(url?: string | null): string | null {
  if (!url) {
    return null;
  }

  if (url.startsWith("/") && !url.startsWith("//")) {
    return url;
  }

  try {
    const parsedUrl = new URL(url);

    return parsedUrl.protocol === "https:" || parsedUrl.protocol === "http:" ? url : null;
  } catch {
    return null;
  }
}
