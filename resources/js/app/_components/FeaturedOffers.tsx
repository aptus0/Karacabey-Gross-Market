import React from "react";
import { AdvertisingBanners } from "./AdvertisingBanners";

export function FeaturedOffers() {
  return (
    <section className="max-w-[1120px] mx-auto px-4 md:px-6 py-8">
      <div className="mb-6">
        <h2 className="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
          Haftalık Fırsatlar
        </h2>
        <p className="text-gray-600">
          Karacabey Gross Market'in en iyi teklifleri bir arada
        </p>
      </div>
      <AdvertisingBanners />
    </section>
  );
}
