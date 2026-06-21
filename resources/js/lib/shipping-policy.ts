export const MIN_ORDER_CENTS = 35_000;
export const FREE_SHIPPING_CENTS = 150_000;
export const STANDARD_SHIPPING_CENTS = 9_990;

export type ShippingCarrierCode = "aras" | "yurtici" | "ptt" | "mng" | "dhlcommerce";

export type ShippingCarrier = {
  code: ShippingCarrierCode;
  name: string;
  description: string;
  eta: string;
  baseCents: number;
  perKgCents: number;
};

export const shippingCarriers: ShippingCarrier[] = [
  {
    code: "aras",
    name: "Aras Kargo",
    description: "Standart yurt içi gönderi ve mağaza teslim desteği.",
    eta: "1-3 iş günü",
    baseCents: 8_990,
    perKgCents: 1_250,
  },
  {
    code: "yurtici",
    name: "Yurtiçi Kargo",
    description: "Fiyat hesaplama sayfası ve kurumsal gönderi akışına uygun yapı.",
    eta: "1-3 iş günü",
    baseCents: 9_990,
    perKgCents: 1_350,
  },
  {
    code: "ptt",
    name: "PTT Kargo",
    description: "Ekonomik gönderi ve geniş teslimat ağı için opsiyonel kanal.",
    eta: "2-5 iş günü",
    baseCents: 7_990,
    perKgCents: 1_050,
  },
  {
    code: "mng",
    name: "MNG Kargo",
    description: "Admin panelden yönetilen standart teslimat opsiyonu.",
    eta: "1-3 iş günü",
    baseCents: 9_990,
    perKgCents: 1_450,
  },
];

export function formatTRYFromCents(cents: number) {
  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: "TRY",
    maximumFractionDigits: 2,
  }).format(cents / 100);
}

export function isKaracabeyLocal(city?: string, district?: string) {
  const normalize = (value = "") => value.toLocaleLowerCase("tr-TR").replace(/ı/g, "i").trim();
  return normalize(city).includes("bursa") && normalize(district).includes("karacabey");
}

export function calculateShippingQuote(input: {
  subtotalCents: number;
  city?: string;
  district?: string;
  weightKg?: number;
  carrier?: ShippingCarrierCode;
}) {
  const local = isKaracabeyLocal(input.city, input.district);
  const carrier = shippingCarriers.find((item) => item.code === input.carrier) ?? shippingCarriers[1];
  const weightKg = Math.max(0, input.weightKg ?? 1);

  if (local) {
    return {
      carrier,
      local: true,
      minOrderApplies: false,
      minimumReached: true,
      freeShippingReached: true,
      shippingCents: 0,
      totalCents: input.subtotalCents,
      message: "Bursa / Karacabey teslimatında standart kargo kuralı uygulanmaz.",
    };
  }

  const minimumReached = input.subtotalCents >= MIN_ORDER_CENTS;
  const freeShippingReached = input.subtotalCents >= FREE_SHIPPING_CENTS;
  const shippingCents = freeShippingReached ? 0 : carrier.baseCents + Math.ceil(weightKg) * carrier.perKgCents;

  return {
    carrier,
    local: false,
    minOrderApplies: true,
    minimumReached,
    freeShippingReached,
    shippingCents,
    totalCents: input.subtotalCents + shippingCents,
    message: freeShippingReached
      ? "1500 TL ve üzeri standart kargo ücretsiz."
      : "Standart kargo ücreti adrese ve desiye göre hesaplanır.",
  };
}
