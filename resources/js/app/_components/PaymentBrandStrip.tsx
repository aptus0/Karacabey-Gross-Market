"use client";

import Image from "next/image";
import { CreditCard } from "lucide-react";

const paymentBrands = [
  { name: "Visa", src: "/assets/brands/payment/visa-20260521.svg" },
  { name: "Mastercard", src: "/assets/brands/payment/mastercard-20260521.svg" },
  { name: "Troy", src: "/assets/brands/payment/troy.svg" },
  { name: "Bankkart", src: "/assets/brands/payment/bankkart-20260521.svg" },
  { name: "PayTR", src: "/assets/brands/payment/paytr.svg" },
];

export function PaymentBrandStrip() {
  return (
    <div className="kgm-payment-brand-strip" aria-label="Desteklenen kart markaları">
      <span><CreditCard size={14} /> Kartla ödeme</span>
      <div>
        {paymentBrands.map((brand) => (
          <Image key={brand.name} src={brand.src} alt={brand.name} width={58} height={34} />
        ))}
      </div>
    </div>
  );
}
