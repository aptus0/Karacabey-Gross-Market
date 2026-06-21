"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { ArrowRight, ShoppingBag, X } from "lucide-react";
import { track } from "@/lib/tracking";

export function CampaignModal() {
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    const hasSeenModal = sessionStorage.getItem("kgm-campaign-modal-seen");
    if (!hasSeenModal) {
      const timer = setTimeout(() => {
        setIsOpen(true);
        track("promotion_view", {
          campaign: "site_acilis_gorselli_kampanya",
          placement: "startup_modal",
        });
      }, 1000);
      return () => clearTimeout(timer);
    }
  }, []);

  function handleClose() {
    setIsOpen(false);
    sessionStorage.setItem("kgm-campaign-modal-seen", "true");
  }

  function handleCampaignClick(action: string) {
    track("promotion_click", {
      campaign: "site_acilis_gorselli_kampanya",
      placement: "startup_modal",
      action,
    });
    handleClose();
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm"
            onClick={handleClose}
          />
          <div className="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 20 }}
              transition={{ type: "spring", stiffness: 300, damping: 30 }}
              className="kgm-campaign-modal"
            >
              <button
                type="button"
                onClick={handleClose}
                className="kgm-campaign-modal__close"
                aria-label="Kampanyayı kapat"
              >
                <X size={18} />
              </button>

              <div className="kgm-campaign-modal__media">
                <Image
                  src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1200&q=82"
                  alt="Taze market ürünleri kampanyası"
                  fill
                  sizes="(max-width: 640px) 100vw, 520px"
                />
                <div className="kgm-campaign-modal__badge">
                  <ShoppingBag size={15} />
                  Haftanın Gross Fırsatları
                </div>
              </div>

              <div className="kgm-campaign-modal__content">
                <p className="kgm-campaign-modal__eyebrow">Karacabey Gross Market</p>
                <h2>Sepete özel taze fırsatlar yayında</h2>
                <p>
                  Günlük market ürünlerinde avantajlı fiyatları kaçırmayın. Kampanyalı ürünleri tek tıkla keşfedin.
                </p>
                <div className="kgm-campaign-modal__actions">
                  <Link
                    href="/kampanyalar"
                    className="kgm-campaign-modal__primary"
                    onClick={() => handleCampaignClick("campaigns")}
                  >
                    Kampanyaları Gör
                    <ArrowRight size={16} />
                  </Link>
                  <Link
                    href="/products"
                    className="kgm-campaign-modal__secondary"
                    onClick={() => handleCampaignClick("products")}
                  >
                    Alışverişe Başla
                  </Link>
                </div>
              </div>
            </motion.div>
          </div>
        </>
      )}
    </AnimatePresence>
  );
}
