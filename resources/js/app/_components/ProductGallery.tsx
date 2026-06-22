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

type GalleryImageCardProps = {
  image: string;
  index: number;
  name: string;
  total: number;
  zoomPosition: { x: number; y: number };
  onPointerMove: (event: PointerEvent<HTMLDivElement>) => void;
  onOpenViewer: () => void;
};

function isLogoPlaceholder(image: string) {
  return /kgm-logo|kg-web|favicon/i.test(image);
}

function EmptyProductGallery({ name }: { name: string }) {
  return (
    <div className="product-gallery product-gallery--empty grid gap-3">
      <div className="product-gallery__image product-gallery__image--empty grid min-h-[360px] place-items-center rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500 shadow-sm sm:min-h-[460px]">
        <div className="flex max-w-xs flex-col items-center gap-3">
          <Package size={64} aria-hidden="true" className="text-orange-500" />
          <strong className="text-base font-semibold text-slate-900">{name}</strong>
          <span className="text-sm font-medium text-slate-500">Ürün görseli hazırlanıyor</span>
        </div>
      </div>
    </div>
  );
}

function GalleryImageCard({
  image,
  index,
  name,
  total,
  zoomPosition,
  onPointerMove,
  onOpenViewer,
}: GalleryImageCardProps) {
  return (
    <div
      className={cn(
        "product-gallery__image",
        "relative flex aspect-square min-h-[360px] w-full items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:min-h-[500px]",
      )}
      onPointerMove={onPointerMove}
      style={
        {
          "--zoom-image": `url("${image}")`,
          "--zoom-x": `${zoomPosition.x}%`,
          "--zoom-y": `${zoomPosition.y}%`,
        } as CSSProperties
      }
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={image}
        alt={index === 0 ? name : `${name} görsel ${index + 1}`}
        className="block h-full max-h-[540px] w-full object-contain"
        loading={index === 0 ? "eager" : "lazy"}
      />
      <div className="product-gallery__meta absolute inset-x-4 bottom-4 z-10 flex items-center justify-between gap-3">
        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
          <Images size={15} />
          {index + 1}/{total}
        </span>
        <button
          type="button"
          onClick={onOpenViewer}
          className="inline-flex items-center gap-1.5 rounded-full bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-500/40"
        >
          <ZoomIn size={15} />
          Yakınlaştır
        </button>
      </div>
      <span className="product-gallery__zoom" aria-hidden="true" />
    </div>
  );
}

function ProductGalleryViewer({
  image,
  name,
  onClose,
}: {
  image: string;
  name: string;
  onClose: () => void;
}) {
  return (
    <div
      className="product-gallery-viewer fixed inset-0 z-[80] flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-label={`${name} görsel yakınlaştırma`}
    >
      <button
        type="button"
        className="product-gallery-viewer__backdrop absolute inset-0 bg-slate-950/70"
        aria-label="Görseli kapat"
        onClick={onClose}
      />
      <div className="product-gallery-viewer__panel relative z-10 flex max-h-[92vh] w-full max-w-5xl items-center justify-center rounded-lg bg-white p-4 shadow-2xl">
        <button
          type="button"
          className="product-gallery-viewer__close absolute right-3 top-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-orange-500/40"
          aria-label="Kapat"
          onClick={onClose}
        >
          <X size={18} />
        </button>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={image} alt={name} className="max-h-[84vh] w-full object-contain" />
      </div>
    </div>
  );
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
    return <EmptyProductGallery name={name} />;
  }

  return (
    <div className="product-gallery grid gap-3">
      <Swiper
        className="product-gallery__main w-full overflow-hidden rounded-lg"
        modules={[FreeMode, Navigation, Thumbs]}
        navigation={safeImages.length > 1}
        onSlideChange={(swiper) => setActiveImage(safeImages[swiper.activeIndex] ?? safeImages[0] ?? "")}
        spaceBetween={12}
        thumbs={{ swiper: thumbsSwiper && !thumbsSwiper.destroyed ? thumbsSwiper : null }}
      >
        {safeImages.map((image, index) => (
          <SwiperSlide key={`${image}-${index}`}>
            <GalleryImageCard
              image={image}
              index={index}
              name={name}
              total={safeImages.length}
              zoomPosition={zoomPosition}
              onPointerMove={handlePointerMove}
              onOpenViewer={() => setViewerOpen(true)}
            />
          </SwiperSlide>
        ))}
      </Swiper>

      {safeImages.length > 1 ? (
        <Swiper
          className="product-gallery__thumbs w-full"
          freeMode
          modules={[FreeMode, Thumbs]}
          onSwiper={setThumbsSwiper}
          slidesPerView="auto"
          spaceBetween={10}
          watchSlidesProgress
        >
          {safeImages.map((image, index) => (
            <SwiperSlide key={`thumb-${image}-${index}`} className="!w-20">
              <button
                type="button"
                aria-label={`${name} görsel ${index + 1}`}
                className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-1 shadow-sm transition hover:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-500/40"
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={image} alt="" className="h-full w-full object-contain" />
              </button>
            </SwiperSlide>
          ))}
        </Swiper>
      ) : null}

      {viewerOpen && selectedImage ? (
        <ProductGalleryViewer
          image={selectedImage}
          name={name}
          onClose={() => setViewerOpen(false)}
        />
      ) : null}
    </div>
  );
}
