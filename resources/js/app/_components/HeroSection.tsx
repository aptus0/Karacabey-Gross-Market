import React from "react";
import { HomeCarousel } from "./HomeCarousel";
import { TrustBar } from "./TrustBar";

export function HeroSection() {
  return (
    <section className="relative">
      {/* Hero Carousel with enhanced styling */}
      <div className="relative">
        <HomeCarousel />
        {/* Gradient overlay for better text contrast */}
        <div className="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-[#f3f6f8] via-[#f3f6f8]/50 to-transparent z-10 pointer-events-none" />
      </div>

      {/* Trust Bar positioned below carousel */}
      <div className="relative z-20 -mt-12 px-4 md:px-0">
        <TrustBar />
      </div>
    </section>
  );
}
