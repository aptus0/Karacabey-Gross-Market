"use client";

import { useState } from "react";
import type { LucideIcon } from "lucide-react";
import {
  BadgeInfo,
  CheckCircle2,
  ClipboardList,
  ListChecks,
  PackageCheck,
  RotateCcw,
  ShieldCheck,
  Store,
  Truck,
} from "lucide-react";
import type { KgmProduct } from "@/lib/catalog";

type ProductInfoAccordionsProps = {
  product: KgmProduct;
};

type ProductInfoTab = "description" | "features" | "delivery" | "seller";

const tabs: Array<{ key: ProductInfoTab; label: string; icon: LucideIcon }> = [
  { key: "description", label: "Açıklama", icon: BadgeInfo },
  { key: "features", label: "Özellikler", icon: ListChecks },
  { key: "delivery", label: "Teslimat & İade", icon: Truck },
  { key: "seller", label: "Satıcı", icon: Store },
];

export function ProductInfoAccordions({ product }: ProductInfoAccordionsProps) {
  const [activeTab, setActiveTab] = useState<ProductInfoTab>("description");
  const stockLabel = product.stock > 0 ? `${product.stock} adet` : "Stok teyidi gerekli";
  const categoryLabel = product.categoryName ?? "Genel";
  const packageLabel = product.unit === "adet" ? "1 Adet" : product.unit;
  const description = product.description?.trim() || `${product.name} için güncel ürün bilgisi Karacabey Gross Market ekibi tarafından hazırlanır.`;
  const specs = [
    ["Marka", product.brand || "Karacabey Gross Market"],
    ["Kategori", categoryLabel],
    ["Paket İçeriği", packageLabel],
    ["Stok Durumu", stockLabel],
    ["Ürün Kodu", product.sku ?? product.slug],
    ...(product.barcode ? [["Barkod", product.barcode]] : []),
  ];

  return (
    <section className="product-info-tabs" aria-label="Ürün detay bilgileri">
      <div className="product-info-tabs__nav" role="tablist" aria-label="Ürün bilgi sekmeleri">
        {tabs.map((tab) => {
          const Icon = tab.icon;

          return (
          <button
            key={tab.key}
            type="button"
            role="tab"
            id={`product-info-tab-${tab.key}`}
            aria-controls={`product-info-panel-${tab.key}`}
            aria-selected={activeTab === tab.key}
            className={activeTab === tab.key ? "is-active" : undefined}
            onClick={() => setActiveTab(tab.key)}
          >
            <Icon size={16} />
            {tab.label}
          </button>
          );
        })}
      </div>

      <div
        className="product-info-tabs__panel"
        role="tabpanel"
        id={`product-info-panel-${activeTab}`}
        aria-labelledby={`product-info-tab-${activeTab}`}
      >
        {activeTab === "description" ? (
          <>
            <div className="product-info-tabs__copy">
              <span className="product-info-tabs__eyebrow">Ürün Bilgisi</span>
              <h2>{product.name}</h2>
              <p>{description}</p>
              <ul>
                <li><CheckCircle2 size={16} /> Güncel fiyat ve stok bilgisi sipariş sırasında tekrar kontrol edilir.</li>
                <li><CheckCircle2 size={16} /> Karacabey Gross Market kalite kontrol sürecinden geçer.</li>
                <li><CheckCircle2 size={16} /> Hızlı teslimat akışına uygun şekilde hazırlanır.</li>
              </ul>
            </div>
            <dl className="product-info-tabs__side">
              {specs.map(([label, value]) => (
                <div key={label}>
                  <PackageCheck size={16} />
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
          </>
        ) : null}

        {activeTab === "features" ? (
          <>
            <div className="product-info-tabs__copy">
              <span className="product-info-tabs__eyebrow">Teknik Detay</span>
              <h2>Ürün özellikleri</h2>
              <p>
                Kategori, paket içeriği ve stok detayları alışveriş öncesinde hızlıca kontrol edilebilir.
              </p>
            </div>
            <dl className="product-info-tabs__side product-info-tabs__side--wide">
              {specs.map(([label, value]) => (
                <div key={label}>
                  <PackageCheck size={16} />
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
          </>
        ) : null}

        {activeTab === "delivery" ? (
          <div className="product-info-tabs__cards">
            <article>
              <Truck size={18} />
              <strong>Hızlı Teslimat</strong>
              <span>Adresinize göre teslimat uygunluğu ve zamanlaması sepet adımında netleşir.</span>
            </article>
            <article>
              <RotateCcw size={18} />
              <strong>İade Süreci</strong>
              <span>İade ve değişim talepleri ürün durumuna göre müşteri hizmetleri tarafından değerlendirilir.</span>
            </article>
            <article>
              <ShieldCheck size={18} />
              <strong>Güvenli Ödeme</strong>
              <span>Ödeme ve kişisel bilgileriniz şifreli bağlantı üzerinden korunur.</span>
            </article>
          </div>
        ) : null}

        {activeTab === "seller" ? (
          <div className="product-info-tabs__cards">
            <article>
              <Store size={18} />
              <strong>Karacabey Gross Market</strong>
              <span>Satıcı puanı, stok kontrolü ve operasyonel hazırlık mağaza ekibi tarafından yönetilir.</span>
            </article>
            <article>
              <ClipboardList size={18} />
              <strong>Ürün Kodu</strong>
              <span>{product.sku ?? product.slug}</span>
            </article>
            <article>
              <CheckCircle2 size={18} />
              <strong>Stok Durumu</strong>
              <span>{stockLabel}</span>
            </article>
          </div>
        ) : null}
      </div>
    </section>
  );
}
