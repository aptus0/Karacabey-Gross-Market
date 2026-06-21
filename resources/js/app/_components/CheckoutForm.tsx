"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import type { ReactNode } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, CreditCard, Loader2, LockKeyhole, MapPin, ShieldCheck, Truck, UserRound } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { AddressMapPicker } from "@/app/_components/AddressMapPicker";
// PaymentBrandStrip moved to CheckoutExperience summary column
import { Button } from "@/app/_components/ui/button";
import { Input } from "@/app/_components/ui/input";
import { ApiRequestError, apiRequest, createClientUID, extractErrorMessage } from "@/lib/api";
import { useAuthStore } from "@/lib/auth-store";
import { useLocationStore } from "@/lib/location-store";
import { calculateShippingQuote, formatTRYFromCents, MIN_ORDER_CENTS, shippingCarriers, type ShippingCarrierCode } from "@/lib/shipping-policy";
import { cn } from "@/lib/utils";

type CheckoutFormItem = {
  productId: number;
  quantity: number;
};

type CheckoutResponse = {
  checkout_url?: string;
  iframe_src?: string;
  direct_payment?: {
    post_url?: string;
    postUrl?: string;
    fields: Record<string, string>;
  };
  payment_unavailable?: boolean;
  message?: string;
  provider_reason?: string;
  trace_id?: string;
};

type CheckoutFormProps = {
  items: CheckoutFormItem[];
  subtotalCents?: number;
  cartToken?: string | null;
  couponCode?: string | null;
  disabled?: boolean;
  onShippingChange?: (shippingCents: number, carrierName: string) => void;
};

function normalizePhone(phone: string) {
  // Remove all non-digit characters
  const digits = phone.replace(/\D/g, "");
  
  // Remove country code if present (+90 prefix)
  if (digits.startsWith("90")) {
    return digits.slice(2);
  }
  
  // Drop leading trunk zero (05xx -> 5xx)
  if (digits.startsWith("0")) {
    return digits.slice(1);
  }
  
  return digits;
}

function formatCardNumber(value: string) {
  return value.replace(/\D/g, "").slice(0, 16).replace(/(.{4})/g, "$1 ").trim();
}

function formatExpiry(value: string) {
  const digits = value.replace(/\D/g, "").slice(0, 4);
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

function isTrustedPayTRURL(rawURL: string) {
  const url = new URL(rawURL, window.location.origin);
  const trustedHost =
    url.origin === window.location.origin ||
    url.hostname === "paytr.com" ||
    url.hostname.endsWith(".paytr.com");

  return trustedHost && url.protocol === "https:" ? url : null;
}

function isTrustedPayTRDirectURL(rawURL: string) {
  const url = new URL(rawURL, window.location.origin);
  const trustedHost = url.hostname === "paytr.com" || url.hostname.endsWith(".paytr.com");

  return trustedHost && url.protocol === "https:" ? url : null;
}

function submitDirectPayTRForm(postURL: URL, fields: Record<string, string>, card: CheckoutFormValues["card"]) {
  const [expiryMonth = "", expiryYear = ""] = card.expiry.split("/");
  const payload = {
    ...fields,
    cc_owner: card.holder_name.trim(),
    card_number: card.number.replace(/\D/g, ""),
    expiry_month: expiryMonth,
    expiry_year: expiryYear,
    cvv: card.cvv.replace(/\D/g, ""),
  };
  const form = document.createElement("form");
  form.method = "POST";
  form.action = postURL.toString();

  Object.entries(payload).forEach(([name, value]) => {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
}

const checkoutSchema = z.object({
  order_type: z.enum(["individual", "corporate"]),
  customer: z.object({
    name: z.string().trim().min(2, "Ad soyad gerekli.").max(60, "Ad soyad çok uzun."),
    email: z.string().trim().email("Geçerli e-posta gir.").max(100, "E-posta çok uzun."),
    phone: z.string().trim().min(5, "Telefon gerekli.").max(20, "Telefon çok uzun."),
    tc_identity: z.string().trim().optional(),
    company_name: z.string().trim().optional(),
    tax_office: z.string().trim().optional(),
    tax_number: z.string().trim().optional(),
  }),
  shipping: z.object({
    city: z.string().trim().max(120, "Şehir çok uzun.").optional(),
    district: z.string().trim().max(120, "İlçe çok uzun.").optional(),
    address: z.string().trim().min(5, "Adres gerekli.").max(400, "Adres çok uzun."),
    lat: z.number().optional(),
    lng: z.number().optional(),
    carrier: z.enum(["aras", "yurtici", "ptt", "mng", "dhlcommerce"] as const).default("yurtici"),
  }),
  card: z.object({
    holder_name: z.string().trim().min(3, "Kart üzerindeki ad soyad gerekli."),
    number: z.string().transform((value) => value.replace(/\D/g, "")).pipe(z.string().min(15, "Geçerli kart numarası gir.").max(16, "Geçerli kart numarası gir.")),
    expiry: z.string().regex(/^(0[1-9]|1[0-2])\/\d{2}$/, "Son kullanma tarihini AA/YY formatında gir."),
    cvv: z.string().regex(/^\d{3,4}$/, "Geçerli CVV gir."),
  }),
}).superRefine((values, ctx) => {
  if (values.order_type === "individual") {
    const identity = values.customer.tc_identity?.replace(/\s/g, "") ?? "";
    if (identity && !/^\d{11}$/.test(identity)) {
      ctx.addIssue({ code: "custom", message: "TC kimlik 11 hane olmalı.", path: ["customer", "tc_identity"] });
    }
  }

  if (values.order_type === "corporate") {
    if (!values.customer.company_name || values.customer.company_name.length < 2) {
      ctx.addIssue({ code: "custom", message: "Firma adı gerekli.", path: ["customer", "company_name"] });
    }
    const taxNumber = values.customer.tax_number?.replace(/\s/g, "") ?? "";
    if (!/^\d{10}$/.test(taxNumber)) {
      ctx.addIssue({ code: "custom", message: "Vergi numarası 10 hane olmalı.", path: ["customer", "tax_number"] });
    }
  }
});

type CheckoutFormInput = z.input<typeof checkoutSchema>;
type CheckoutFormValues = z.output<typeof checkoutSchema>;

type CargoOptionPayload = {
  code: string;
  name: string;
  logo_url?: string;
  price_cents: number;
  original_price_cents?: number;
  is_free?: boolean;
  free_threshold_cents?: number;
  estimated_days?: {
    min?: number;
    max?: number;
  };
};

function normalizeCarrierCode(code: string): ShippingCarrierCode {
  const normalized = code.toLocaleLowerCase("tr-TR").replaceAll("ı", "i").replace(/\s+/g, "");
  if (normalized.includes("yurtici") || normalized.includes("yurtiçi")) return "yurtici";
  if (normalized.includes("aras")) return "aras";
  if (normalized.includes("ptt")) return "ptt";
  if (normalized.includes("mng")) return "mng";
  if (normalized.includes("dhl")) return "dhlcommerce";
  return "yurtici";
}

function cargoOptionToCarrier(option: CargoOptionPayload) {
  const min = option.estimated_days?.min ?? 1;
  const max = option.estimated_days?.max ?? min;

  return {
    code: normalizeCarrierCode(option.code),
    name: option.name,
    description: option.is_free ? "Bu sipariş için ücretsiz kargo." : "Sipariş için uygun kargo seçeneği.",
    eta: min === max ? `${min} iş günü` : `${min}-${max} iş günü`,
    baseCents: option.price_cents,
    perKgCents: 0,
  };
}

export function CheckoutForm({
  items,
  subtotalCents = 0,
  cartToken,
  couponCode,
  disabled = false,
  onShippingChange,
}: CheckoutFormProps) {
  const token = useAuthStore((state) => state.token);
  const savedLocation = useLocationStore((state) => state.location);
  const [error, setError] = useState<string | null>(null);
  const [isRedirecting, setIsRedirecting] = useState(false);
  const [managedCarriers, setManagedCarriers] = useState(shippingCarriers);
  const checkoutKeyRef = useRef(createClientUID("chk"));
  const paymentUIDRef = useRef(createClientUID("pay"));
  const cargoOptionsCacheRef = useRef(new Map<number, ReturnType<typeof cargoOptionToCarrier>[]>());
  const cargoOptionsBlockedUntilRef = useRef(0);
  
  const {
    formState: { errors, isSubmitting },
    getValues,
    handleSubmit,
    register,
    setValue,
    watch,
  } = useForm<CheckoutFormInput, unknown, CheckoutFormValues>({
    resolver: zodResolver(checkoutSchema),
    defaultValues: {
      order_type: "individual",
      shipping: {
        city: savedLocation?.city ?? "Bursa",
        district: savedLocation?.district ?? "Karacabey",
        address: savedLocation?.address ?? "",
        lat: savedLocation?.lat,
        lng: savedLocation?.lng,
        carrier: "yurtici",
      },
      card: {
        holder_name: "",
        number: "",
        expiry: "",
        cvv: "",
      },
    },
  });

  const orderType = watch("order_type");
  const city = watch("shipping.city");
  const district = watch("shipping.district");
  const selectedCarrier = watch("shipping.carrier");
  const selectedManagedCarrier = managedCarriers.find((carrier) => carrier.code === selectedCarrier) ?? managedCarriers[0];
  
  const quote = useMemo(
    () => calculateShippingQuote({ subtotalCents, city, district, carrier: selectedCarrier as ShippingCarrierCode, weightKg: Math.max(items.length, 1) }),
    [city, district, items.length, subtotalCents, selectedCarrier],
  );
  const displayShippingCents = quote.local ? 0 : selectedManagedCarrier?.baseCents ?? quote.shippingCents;
  const displayCarrierName = selectedManagedCarrier?.name ?? quote.carrier.name;

  useEffect(() => {
    let cancelled = false;
    const cachedOptions = cargoOptionsCacheRef.current.get(subtotalCents);

    if (cachedOptions) {
      setManagedCarriers(cachedOptions);
      return;
    }

    if (Date.now() < cargoOptionsBlockedUntilRef.current) {
      return;
    }

    const controller = new AbortController();

    apiRequest<CargoOptionPayload[]>(`/api/v1/cargo/options?order_cents=${subtotalCents}`, {
      timeoutMs: 6_000,
      signal: controller.signal,
    })
      .then((options) => {
        if (cancelled || !Array.isArray(options) || options.length === 0) return;
        const nextCarriers = options.map(cargoOptionToCarrier);
        cargoOptionsCacheRef.current.set(subtotalCents, nextCarriers);
        setManagedCarriers(nextCarriers);
        const activeCarrier = getValues("shipping.carrier");
        if (!nextCarriers.some((carrier) => carrier.code === activeCarrier)) {
          setValue("shipping.carrier", nextCarriers[0].code, { shouldValidate: true, shouldDirty: true });
        }
      })
      .catch((caughtError) => {
        if (caughtError instanceof ApiRequestError && [502, 503, 504].includes(caughtError.status)) {
          cargoOptionsBlockedUntilRef.current = Date.now() + 30_000;
        }
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [getValues, setValue, subtotalCents]);

  useEffect(() => {
    onShippingChange?.(displayShippingCents, displayCarrierName);
  }, [displayCarrierName, displayShippingCents, onShippingChange]);

  async function submitCheckout(values: CheckoutFormValues) {
    setError(null);

    if (items.length === 0) {
      setError("Sepet boş.");
      return;
    }

    const currentQuote = calculateShippingQuote({
      subtotalCents,
      city: values.shipping.city,
      district: values.shipping.district,
      carrier: (values.shipping.carrier ?? "yurtici") as ShippingCarrierCode,
      weightKg: Math.max(items.length, 1),
    });
    const currentCarrier = managedCarriers.find((carrier) => carrier.code === values.shipping.carrier);
    const currentShippingCents = currentQuote.local ? 0 : currentCarrier?.baseCents ?? currentQuote.shippingCents;

    if (currentQuote.minOrderApplies && !currentQuote.minimumReached) {
      setError(`Minimum sipariş tutarı ${formatTRYFromCents(MIN_ORDER_CENTS)}.`);
      return;
    }

    try {
      const shippingPayload = {
        city: values.shipping.city,
        district: values.shipping.district,
        address: values.shipping.address,
        carrier: values.shipping.carrier,
        lat: values.shipping.lat,
        lng: values.shipping.lng,
      };
      const payload = await apiRequest<CheckoutResponse>("/api/v1/c", {
        timeoutMs: 15_000,
        method: "POST",
        headers: {
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(!token && cartToken ? { "X-Cart-Token": cartToken } : {}),
          "X-Checkout-Key": checkoutKeyRef.current,
          "X-Payment-UID": paymentUIDRef.current,
          "X-Idempotency-Key": paymentUIDRef.current,
        },
        body: JSON.stringify({
          order_type: values.order_type,
          customer: {
            ...values.customer,
            phone: normalizePhone(values.customer.phone),
          },
          shipping: shippingPayload,
          invoice: {
            type: values.order_type,
            tc_identity: values.customer.tc_identity,
            company_name: values.customer.company_name,
            tax_office: values.customer.tax_office,
            tax_number: values.customer.tax_number,
          },
          shipping_quote: {
            carrier: currentQuote.carrier.code,
            local_delivery: currentQuote.local,
            shipping_cents: currentShippingCents,
          },
          cart_token: !token ? cartToken ?? undefined : undefined,
          coupon_code: couponCode ?? undefined,
          checkout_key: checkoutKeyRef.current,
          checkout_uid: checkoutKeyRef.current,
          payment_uid: paymentUIDRef.current,
          payment_flow: "direct",
          items: items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
        }),
      });

      const directPayment = payload.direct_payment;
      const directPostURL = directPayment?.post_url ?? directPayment?.postUrl;
      if (directPayment && directPostURL) {
        const trustedDirectURL = isTrustedPayTRDirectURL(directPostURL);
        if (!trustedDirectURL) {
          setError("Güvenli PayTR ödeme adresi doğrulanamadı.");
          return;
        }
        setIsRedirecting(true);
        submitDirectPayTRForm(trustedDirectURL, directPayment.fields, values.card);
        return;
      }

      const checkoutUrl = payload.checkout_url ?? payload.iframe_src;
      if (!checkoutUrl || payload.payment_unavailable) {
        setError(payload.message ?? payload.provider_reason ?? "Ödeme başlatılamadı.");
        return;
      }

      const paymentUrl = isTrustedPayTRURL(checkoutUrl);
      if (!paymentUrl) {
        setError("Güvenli ödeme adresi doğrulanamadı.");
        return;
      }

      setIsRedirecting(true);
      window.location.assign(paymentUrl.toString());
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(extractErrorMessage(err.payload, err.message));
        return;
      }
      setError("Ödeme başlatılamadı.");
    }
  }

  return (
    <form className="space-y-6" onSubmit={handleSubmit(submitCheckout)} noValidate>
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 className="text-lg font-medium text-slate-900 flex items-center gap-2">
          <UserRound size={20} className="text-slate-400" />
          İletişim & Fatura Bilgileri
        </h2>
        <div className="mt-4 grid grid-cols-2 gap-2" role="radiogroup" aria-label="Sipariş tipi">
          <label className={cn("flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors", orderType === "individual" ? "border-orange-500 bg-orange-50/50" : "border-slate-200 hover:bg-slate-50")}>
            <input type="radio" value="individual" {...register("order_type")} className="h-4 w-4 border-slate-300 text-orange-600 focus:ring-orange-600" />
            <span className="text-sm font-medium text-slate-900">Bireysel Sipariş</span>
          </label>
          <label className={cn("flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors", orderType === "corporate" ? "border-orange-500 bg-orange-50/50" : "border-slate-200 hover:bg-slate-50")}>
            <input type="radio" value="corporate" {...register("order_type")} className="h-4 w-4 border-slate-300 text-orange-600 focus:ring-orange-600" />
            <span className="text-sm font-medium text-slate-900">Kurumsal Sipariş (Fatura)</span>
          </label>
        </div>
        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <Field label="Ad Soyad" error={errors.customer?.name?.message}><Input {...register("customer.name")} autoComplete="name" className="shadow-sm" /></Field>
          </div>
          <Field label="Telefon" error={errors.customer?.phone?.message}><Input {...register("customer.phone")} autoComplete="tel" className="shadow-sm" /></Field>
          <Field label="E-posta" error={errors.customer?.email?.message}><Input {...register("customer.email")} type="email" autoComplete="email" className="shadow-sm" /></Field>
          {orderType === "individual" ? (
            <div className="sm:col-span-2">
              <Field label="TC Kimlik No (Opsiyonel)" error={errors.customer?.tc_identity?.message}><Input {...register("customer.tc_identity")} inputMode="numeric" maxLength={11} className="shadow-sm" /></Field>
            </div>
          ) : null}
        </div>

        {orderType === "corporate" ? (
          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Field label="Firma Ünvanı" error={errors.customer?.company_name?.message}><Input {...register("customer.company_name")} className="shadow-sm" /></Field>
            </div>
            <Field label="Vergi Dairesi" error={errors.customer?.tax_office?.message}><Input {...register("customer.tax_office")} className="shadow-sm" /></Field>
            <Field label="Vergi No" error={errors.customer?.tax_number?.message}><Input {...register("customer.tax_number")} inputMode="numeric" maxLength={10} className="shadow-sm" /></Field>
          </div>
        ) : null}
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 className="text-lg font-medium text-slate-900 flex items-center gap-2">
          <MapPin size={20} className="text-slate-400" />
          Teslimat Adresi
        </h2>
        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Şehir" error={errors.shipping?.city?.message}><Input {...register("shipping.city")} className="shadow-sm" /></Field>
          <Field label="İlçe" error={errors.shipping?.district?.message}><Input {...register("shipping.district")} className="shadow-sm" /></Field>
        </div>
        
        {/* Modernized Cargo Selection Radios */}
        <div className="kgm-field">
          <span><Truck size={14} /> Kargo seçimi</span>
          <div className="kgm-cargo-picker" role="radiogroup" aria-label="Kargo seçeneği">
            {managedCarriers.map((carrier) => {
              const isSelected = selectedCarrier === carrier.code;
              return (
                <label
                  key={carrier.code}
                  className={cn("kgm-cargo-card", isSelected && "is-active")}
                >
                  <input
                    type="radio"
                    value={carrier.code}
                    {...register("shipping.carrier")}
                    className="sr-only"
                  />
                  <div className="kgm-cargo-card__left">
                    <div className={cn("kgm-cargo-card__radio-circle", isSelected && "is-checked")} />
                    <div className="kgm-cargo-card__info">
                      <span className="kgm-cargo-card__name">{carrier.name}</span>
                      <span className="kgm-cargo-card__eta">{carrier.eta}</span>
                    </div>
                  </div>
                  <span className="kgm-cargo-card__price">
                    {quote.local || carrier.baseCents === 0 ? "Ücretsiz" : formatTRYFromCents(carrier.baseCents)}
                  </span>
                </label>
              );
            })}
          </div>
          {errors.shipping?.carrier?.message ? (
            <small className="text-red-500 text-xs mt-1">{errors.shipping?.carrier?.message}</small>
          ) : null}
        </div>

        <div className="mt-4">
          <Field label="Açık Adres" error={errors.shipping?.address?.message}>
            <textarea {...register("shipping.address")} rows={3} className="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-orange-600 sm:text-sm sm:leading-6" />
          </Field>
        </div>
        <div className="mt-4">
          <AddressMapPicker
            compact
            title="Teslimat konumunu algıla"
            description="Google Maps destekli konum, adres alanıyla birlikte kullanılacak."
            onLocation={(location) => {
              setValue("shipping.address", location.address ?? location.label, { shouldValidate: true, shouldDirty: true });
              setValue("shipping.city", location.city ?? "Bursa", { shouldValidate: true, shouldDirty: true });
              setValue("shipping.district", location.district ?? "Karacabey", { shouldValidate: true, shouldDirty: true });
              setValue("shipping.lat", location.lat, { shouldDirty: true });
              setValue("shipping.lng", location.lng, { shouldDirty: true });
            }}
          />
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 className="text-lg font-medium text-slate-900 flex items-center gap-2">
          <CreditCard size={20} className="text-slate-400" />
          Güvenli Ödeme
        </h2>

        <div className="mt-4 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-800 to-orange-700 p-5 text-white shadow-lg">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-[0.18em] text-orange-200">Karacabey Gross</span>
            <CreditCard size={26} />
          </div>
          <p className="mt-8 font-mono text-lg tracking-[0.16em]">{watch("card.number") || "•••• •••• •••• ••••"}</p>
          <div className="mt-5 flex items-end justify-between">
            <div>
              <span className="block text-[10px] uppercase tracking-wider text-slate-300">Kart Sahibi</span>
              <strong className="text-sm">{watch("card.holder_name") || "AD SOYAD"}</strong>
            </div>
            <div className="text-right">
              <span className="block text-[10px] uppercase tracking-wider text-slate-300">Son Kullanma</span>
              <strong className="text-sm">{watch("card.expiry") || "AA/YY"}</strong>
            </div>
          </div>
        </div>

        <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <Field label="Kart Üzerindeki Ad Soyad" error={errors.card?.holder_name?.message}>
              <Input {...register("card.holder_name")} autoComplete="cc-name" className="h-12 shadow-sm" />
            </Field>
          </div>
          <div className="sm:col-span-2">
            <Field label="Kart Numarası" error={errors.card?.number?.message}>
              <Input
                {...register("card.number")}
                inputMode="numeric"
                autoComplete="cc-number"
                maxLength={19}
                className="h-12 font-mono tracking-wider shadow-sm"
                onChange={(event) => setValue("card.number", formatCardNumber(event.target.value), { shouldDirty: true, shouldValidate: true })}
              />
            </Field>
          </div>
          <Field label="Son Kullanma (AA/YY)" error={errors.card?.expiry?.message}>
            <Input
              {...register("card.expiry")}
              inputMode="numeric"
              autoComplete="cc-exp"
              maxLength={5}
              className="h-12 shadow-sm"
              onChange={(event) => setValue("card.expiry", formatExpiry(event.target.value), { shouldDirty: true, shouldValidate: true })}
            />
          </Field>
          <Field label="CVV" error={errors.card?.cvv?.message}>
            <Input {...register("card.cvv")} type="password" inputMode="numeric" autoComplete="cc-csc" maxLength={4} className="h-12 shadow-sm" />
          </Field>
        </div>

        <div className="mt-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3.5">
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white">
            <LockKeyhole size={17} />
          </span>
          <p className="text-xs leading-5 text-slate-600">
            Kart bilgileriniz mağaza sunucularına gönderilmez veya kaydedilmez; doğrudan PayTR güvenli ödeme altyapısına iletilir.
          </p>
        </div>

        <div className="mt-4 flex flex-wrap gap-3 text-xs text-slate-500">
          <span className="flex items-center gap-1.5"><ShieldCheck size={14} className="text-green-600" /> PayTR PCI-DSS Altyapısı</span>
          <span className="flex items-center gap-1.5"><LockKeyhole size={14} /> Kart kaydedilmez</span>
          <span className="flex items-center gap-1.5"><CheckCircle2 size={14} /> 3D Secure Doğrulama</span>
        </div>
      </section>

      {error ? (
        <div className="mt-6 rounded-md bg-red-50 p-4">
          <p className="text-sm text-red-700">{error}</p>
        </div>
      ) : null}

      <div className="sticky bottom-3 z-10 rounded-xl border border-slate-200 bg-white/95 p-2 shadow-[0_12px_32px_rgba(15,23,42,0.12)] backdrop-blur">
        <Button 
          className="h-12 w-full text-sm font-semibold shadow-md transition-all hover:bg-orange-700 hover:shadow-lg focus:ring-2 focus:ring-orange-600 focus:ring-offset-2 disabled:opacity-50 disabled:shadow-none" 
          type="submit" 
          disabled={isSubmitting || isRedirecting || disabled || items.length === 0}
        >
          {isSubmitting || isRedirecting ? <Loader2 size={18} className="animate-spin mr-2" /> : <LockKeyhole size={18} className="mr-2" />}
          Kartla Güvenli Ödeme Yap
        </Button>
      </div>
    </form>
  );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block w-full">
      <span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>
      {children}
      {error ? <small className="mt-1.5 block text-xs font-medium text-red-600">{error}</small> : null}
    </label>
  );
}
