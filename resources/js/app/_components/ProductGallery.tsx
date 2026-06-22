"use client";

import { useEffect, useMemo, useState, type CSSProperties, type PointerEvent } from "react";
import { Images, Package, X, ZoomIn } from "lucide-react";
import type { Swiper as SwiperType } from "swiper";
import { FreeMode, Navigation, Thumbs } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { cn } from "@/lib/utils";
import { normalizeProductImageList } from "@/lib/media";

type ProductGalleryProps = {
  images: string[];
  name: string;
};

function isLogoPlaceholder(image: string) {
  return /kgm-logo|kg-web|favicon/i.test(image);
}

export function ProductGallery({ images, name }: ProductGalleryProps) {
  const safeImages = useMemo(
    () => normalizeProductImageList(images).filter((image) => !isLogoPlaceholder(image)),
    [images],
  );
  const [thumbsSwiper, setThumbsSwiper] = useState<SwiperType | null>(null);
  const [activeImage, setActiveImage] = useState(safeImages[0] ?? "");
  const [viewerOpen, setViewerOpen] = useState(false);
  const [zoomPosition, setZoomPosition] = useState({ x: 50, y: 50 });
  const selectedImage = activeImage || safeImages[0];

  useEffect(() => {
    setActiveImage(safeImages[0] ?? "");
  }, [safeImages]);

  function handlePointerMove(event: PointerEvent<HTMLDivElement>) {
    const bounds = event.currentTarget.getBoundingClientRect();
    const x = ((event.clientX - bounds.left) / bounds.width) * 100;
    const y = ((event.clientY - bounds.top) / bounds.height) * 100;

    setZoomPosition({
      x: Math.min(100, Math.max(0, x)),
      y: Math.min(100, Math.max(0, y)),
    });
  }

  if (safeImages.length === 0) {
    return (
      <div className="product-gallery product-gallery--empty">
        <div className="product-gallery__image product-gallery__image--empty">
          <Package size={64} aria-hidden="true" />
          <strong>{name}</strong>
          <span>Ürün görseli hazırlanıyor</span>
        </div>
      </div>
    );
  }

  return (
    <div className="product-gallery">
      <Swiper
        className="product-gallery__main"
        modules={[FreeMode, Navigation, Thumbs]}
        navigation={safeImages.length > 1}
        onSlideChange={(swiper) => setActiveImage(safeImages[swiper.activeIndex] ?? safeImages[0] ?? "")}
        spaceBetween={12}
        thumbs={{ swiper: thumbsSwiper && !thumbsSwiper.destroyed ? thumbsSwiper : null }}
      >
        {safeImages.map((image, index) => (
          <SwiperSlide key={`${image}-${index}`}>
            <div
              className={cn("product-gallery__image")}
              onPointerMove={handlePointerMove}
              style={
                {
                  "--zoom-image": `url("${image}")`,
                  "--zoom-x": `${zoomPosition.x}%`,
                  "--zoom-y": `${zoomPosition.y}%`,
                } as CSSProperties
              }
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={image} alt={index === 0 ? name : `${name} görsel ${index + 1}`} />
              <div className="product-gallery__meta">
                <span>
                  <Images size={15} />
                  {index + 1}/{safeImages.length}
                </span>
                <button type="button" onClick={() => setViewerOpen(true)}>
                  <ZoomIn size={15} />
                  Yakınlaştır
                </button>
              </div>
              <span className="product-gallery__zoom" aria-hidden="true" />
            </div>
          </SwiperSlide>
        ))}
      </Swiper>

      {safeImages.length > 1 ? (
        <Swiper
          className="product-gallery__thumbs"
          freeMode
          modules={[FreeMode, Thumbs]}
          onSwiper={setThumbsSwiper}
          slidesPerView="auto"
          spaceBetween={10}
          watchSlidesProgress
        >
          {safeImages.map((image, index) => (
            <SwiperSlide key={`thumb-${image}-${index}`}>
              <button type="button" aria-label={`${name} görsel ${index + 1}`}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={image} alt="" />
              </button>
            </SwiperSlide>
          ))}
        </Swiper>
      ) : null}

      {viewerOpen && selectedImage ? (
        <div className="product-gallery-viewer" role="dialog" aria-modal="true" aria-label={`${name} görsel yakınlaştırma`}>
          <button
            type="button"
            className="product-gallery-viewer__backdrop"
            aria-label="Görseli kapat"
            onClick={() => setViewerOpen(false)}
          />
          <div className="product-gallery-viewer__panel">
            <button
              type="button"
              className="product-gallery-viewer__close"
              aria-label="Kapat"
              onClick={() => setViewerOpen(false)}
            >
              <X size={18} />
            </button>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={selectedImage} alt={name} />
          </div>
        </div>
      ) : null}
    </div>
  );
}
