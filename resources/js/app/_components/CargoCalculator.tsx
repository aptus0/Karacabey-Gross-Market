"use client";

import { Calculator, MapPin, Package, Truck } from "lucide-react";
import { useMemo, useState } from "react";
import { calculateShippingQuote, formatTRYFromCents, FREE_SHIPPING_CENTS, MIN_ORDER_CENTS, shippingCarriers, type ShippingCarrierCode } from "@/lib/shipping-policy";

export function CargoCalculator() {
  const [carrier, setCarrier] = useState<ShippingCarrierCode>("yurtici");
  const [subtotal, setSubtotal] = useState(750);
  const [weight, setWeight] = useState(3);
  const [city, setCity] = useState("Bursa");
  const [district, setDistrict] = useState("Karacabey");

  const quote = useMemo(() => calculateShippingQuote({
    subtotalCents: Math.max(0, Math.round(subtotal * 100)),
    weightKg: weight,
    city,
    district,
    carrier,
  }), [carrier, city, district, subtotal, weight]);

  return (
    <section className="kgm-cargo-mini">
      <div className="kgm-cargo-mini__form">
        <div className="kgm-section-title kgm-section-title--compact">
          <span><Calculator size={15} /> Kargo Hesaplama</span>
          <h1>Yaklaşık kargo tutarı</h1>
        </div>

        <div className="kgm-form-grid">
          <label>
            <span>Firma</span>
            <select value={carrier} onChange={(event) => setCarrier(event.target.value as ShippingCarrierCode)}>
              {shippingCarriers.map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
            </select>
          </label>
          <label>
            <span>Sepet Tutarı</span>
            <input type="number" min="0" value={subtotal} onChange={(event) => setSubtotal(Number(event.target.value))} />
          </label>
          <label>
            <span>Ağırlık / Desi</span>
            <input type="number" min="1" value={weight} onChange={(event) => setWeight(Number(event.target.value))} />
          </label>
          <label>
            <span>Şehir</span>
            <input value={city} onChange={(event) => setCity(event.target.value)} />
          </label>
          <label>
            <span>İlçe</span>
            <input value={district} onChange={(event) => setDistrict(event.target.value)} />
          </label>
        </div>
      </div>

      <aside className="kgm-cargo-mini__result">
        <div className="kgm-cargo-mini__carrier"><Truck size={17} /> {quote.carrier.name}</div>
        <strong>{formatTRYFromCents(quote.shippingCents)}</strong>
        <span>{quote.local ? "Yerel teslimat" : quote.freeShippingReached ? "Ücretsiz kargo" : "Tahmini kargo"}</span>
        <p>{quote.message}</p>
        <div className="kgm-cargo-mini__rules">
          <div><Package size={14} /> Min. {formatTRYFromCents(MIN_ORDER_CENTS)}</div>
          <div><Truck size={14} /> {formatTRYFromCents(FREE_SHIPPING_CENTS)} üzeri ücretsiz</div>
          <div><MapPin size={14} /> Bursa / Karacabey yerel</div>
        </div>
      </aside>
    </section>
  );
}
