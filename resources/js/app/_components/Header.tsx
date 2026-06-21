"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import {
  ChevronDown,
  ChevronRight,
  Grid3X3,
  Heart,
  Loader2,
  LogOut,
  MapPin,
  Navigation,
  ShoppingCart,
  User,
} from "lucide-react";

import { CartSheet } from "@/app/_components/CartSheet";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { SearchBar } from "@/app/_components/SearchBar";
import { useAuthStore } from "@/lib/auth-store";
import { cartItemCount, formatCartMoney } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";
import {
  defaultCategoryMenu,
  fetchCategoryMenu,
  type CategoryMenuItem,
} from "@/lib/category-menu";
import { reverseGeocodeDeliveryLocation, useLocationStore, type DeliveryLocation } from "@/lib/location-store";

type HeaderProps = {
  compact?: boolean;
  hideOnMobile?: boolean;
};

export function Header({ compact = false, hideOnMobile = false }: HeaderProps) {
  const [categoryMenu, setCategoryMenu] =
    useState<CategoryMenuItem[]>(defaultCategoryMenu);
  const [isCartAnimating, setIsCartAnimating] = useState(false);
  const [isLocating, setIsLocating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);
  const [locationOpen, setLocationOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const [manualCity, setManualCity] = useState("Bursa");
  const [manualDistrict, setManualDistrict] = useState("");
  const [manualAddress, setManualAddress] = useState("");
  const prevCartCount = useRef(0);

  const cartCount = useCartStore((state) => cartItemCount(state.items));
  const cartTotalCents = useCartStore((state) => state.total_cents);
  const openCartSheet = useCartStore((state) => state.openSheet);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);
  const deliveryLocation = useLocationStore((state) => state.location);
  const setDeliveryLocation = useLocationStore((state) => state.setLocation);
  const firstName = (user?.name ?? "Hesabım").split(" ")[0];
  const cartTotalLabel = cartCount > 0 ? formatCartMoney(cartTotalCents) : "Sepet";
  const locationLabel = deliveryLocation?.district ?? deliveryLocation?.label ?? "Konum seç";

  useEffect(() => {
    const controller = new AbortController();

    fetchCategoryMenu(controller.signal)
      .then(setCategoryMenu)
      .catch(() => setCategoryMenu(defaultCategoryMenu));

    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (cartCount > prevCartCount.current) {
      setIsCartAnimating(true);
      const timer = setTimeout(() => setIsCartAnimating(false), 260);
      prevCartCount.current = cartCount;
      return () => clearTimeout(timer);
    }
    prevCartCount.current = cartCount;
  }, [cartCount]);

  function detectDeliveryLocation() {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setLocationError("Konum desteklenmiyor.");
      return;
    }

    setIsLocating(true);
    setLocationError(null);
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const lat = Number(position.coords.latitude.toFixed(6));
        const lng = Number(position.coords.longitude.toFixed(6));
        const resolved = await reverseGeocodeDeliveryLocation(lat, lng).catch(() => ({
          address: undefined,
          city: undefined,
          district: undefined,
          label: `${lat}, ${lng}`,
        }));
        const location: DeliveryLocation = {
          lat,
          lng,
          label: resolved.label,
          address: resolved.address,
          city: resolved.city,
          district: resolved.district,
          source: "browser",
          updatedAt: new Date().toISOString(),
        };

        setDeliveryLocation(location);
        setLocationOpen(false);
        setIsLocating(false);
      },
      () => {
        setIsLocating(false);
        setLocationError("Konum izni alınamadı.");
      },
      { enableHighAccuracy: true, timeout: 9000, maximumAge: 60_000 },
    );
  }

  function saveManualLocation() {
    const district = manualDistrict.trim();
    const city = manualCity.trim() || "Bursa";
    const address = manualAddress.trim();

    setDeliveryLocation({
      lat: 0,
      lng: 0,
      label: district ? `${district}, ${city}` : city,
      address: address || undefined,
      city,
      district: district || undefined,
      source: "manual",
    });
    setLocationOpen(false);
  }

  async function handleLogout() {
    await logout();
    setAccountOpen(false);
    window.location.href = "/";
  }

  return (
    <header
      className={`kgm-header-v17 sticky top-0 z-40 border-b border-slate-200 bg-white ${
        compact ? "kgm-header-v17--compact" : ""
      } ${hideOnMobile ? "hidden lg:block" : ""}`}
    >
      <div className="mx-auto grid h-[72px] max-w-[1440px] grid-cols-[240px_minmax(0,1fr)_auto] items-center gap-6 px-5 xl:px-8">
        <Link
          href="/"
          className="inline-flex min-w-0 items-center overflow-hidden"
          aria-label="Karacabey Gross Market ana sayfa"
        >
          <KgmLogo variant="header" compact={compact} />
        </Link>

        {!compact && (
          <div className="kgm-header-search-slot min-w-0">
            <SearchBar />
          </div>
        )}

        <div className="flex shrink-0 items-center gap-3">
          <button
            type="button"
            className="kgm-header-location inline-flex h-11 max-w-[190px] items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 hover:border-orange-200 hover:text-orange-600"
            onClick={() => setLocationOpen(true)}
            disabled={isLocating}
            title={locationError ?? deliveryLocation?.address ?? "Teslimat konumunu algıla"}
          >
            {isLocating ? <Loader2 size={16} className="animate-spin" /> : <MapPin size={16} />}
            <span className="truncate">{isLocating ? "Algılanıyor" : locationLabel}</span>
            {!isLocating ? <Navigation size={13} className="text-orange-500" /> : null}
          </button>

          <Link
            href="/favorites"
            className="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 hover:border-orange-200 hover:text-orange-600"
            aria-label="Favoriler"
          >
            <Heart size={18} />
          </Link>

          {isAuthenticated ? (
            <div className="relative">
              <button
                type="button"
                className="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 hover:border-orange-200 hover:text-orange-600"
                onClick={() => setAccountOpen((open) => !open)}
                aria-expanded={accountOpen}
              >
                <User size={17} />
                {firstName}
                <ChevronDown size={14} />
              </button>
              {accountOpen ? (
                <div className="kgm-account-dropdown">
                  <div className="kgm-account-dropdown__head">
                    <strong>{user?.name ?? "Hesabım"}</strong>
                    <span>{user?.phone ?? user?.email ?? "Karacabey Gross Market"}</span>
                  </div>
                  <Link href="/account" onClick={() => setAccountOpen(false)}>Kullanıcı Profili</Link>
                  <Link href="/account/orders" onClick={() => setAccountOpen(false)}>Siparişlerim</Link>
                  <Link href="/account/addresses" onClick={() => setAccountOpen(false)}>Adreslerim</Link>
                  <Link href="/favorites" onClick={() => setAccountOpen(false)}>Favorilerim</Link>
                  <button type="button" onClick={handleLogout}>
                    <LogOut size={15} /> Çıkış Yap
                  </button>
                </div>
              ) : null}
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <Link
                href="/auth/register"
                className="inline-flex h-11 items-center rounded-md border border-orange-200 bg-orange-50 px-4 text-xs font-semibold text-orange-700 hover:bg-orange-100"
              >
                Kayıt Ol
              </Link>
              <Link
                href="/auth/login"
                className="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 hover:border-orange-200 hover:text-orange-600"
              >
                <User size={17} />
                Giriş Yap
              </Link>
            </div>
          )}

          <button
            type="button"
            className={`kgm-cart-plain relative inline-flex h-11 items-center gap-2 rounded-md bg-orange-600 px-4 text-xs font-semibold text-white hover:bg-orange-700 ${
              isCartAnimating ? "kgm-cart-plain--pulse" : ""
            }`}
            onClick={openCartSheet}
          >
            <ShoppingCart size={18} />
            <span>{cartTotalLabel}</span>
            {cartCount > 0 && (
              <span className="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-950 px-1 text-[10px] font-semibold text-white">
                {cartCount}
              </span>
            )}
          </button>
        </div>
      </div>

      {!compact && <HeaderCategoryMegaNav items={categoryMenu} />}

      {locationOpen ? (
        <div className="kgm-location-modal" role="dialog" aria-modal="true" aria-label="Teslimat konumu seç">
          <button type="button" className="kgm-location-modal__backdrop" onClick={() => setLocationOpen(false)} aria-label="Kapat" />
          <div className="kgm-location-modal__panel">
            <div className="kgm-location-modal__head">
              <div>
                <strong>Teslimat Konumu</strong>
                <span>Konumunuzu seçin, sepet ve teslimat akışı buna göre hızlansın.</span>
              </div>
              <button type="button" onClick={() => setLocationOpen(false)} aria-label="Konum penceresini kapat">×</button>
            </div>
            <button type="button" className="kgm-location-detect" onClick={detectDeliveryLocation} disabled={isLocating}>
              {isLocating ? <Loader2 size={17} className="animate-spin" /> : <Navigation size={17} />}
              {isLocating ? "Konum algılanıyor..." : "Konumumu algıla"}
            </button>
            {locationError ? <p className="kgm-location-error">{locationError}</p> : null}
            <div className="kgm-location-form">
              <label>
                İl
                <input value={manualCity} onChange={(event) => setManualCity(event.target.value)} placeholder="Bursa" />
              </label>
              <label>
                İlçe
                <input value={manualDistrict} onChange={(event) => setManualDistrict(event.target.value)} placeholder="Karacabey, Mustafakemalpaşa..." />
              </label>
              <label>
                Adres / mahalle
                <textarea value={manualAddress} onChange={(event) => setManualAddress(event.target.value)} placeholder="Mahalle, cadde veya teslimat notu" />
              </label>
              <button type="button" onClick={saveManualLocation}>Konumu Kaydet</button>
            </div>
          </div>
        </div>
      ) : null}

      <CartSheet />
    </header>
  );
}

function HeaderCategoryMegaNav({ items }: { items: CategoryMenuItem[] }) {
  const [activeSlug, setActiveSlug] = useState<string | null>(
    items[0]?.slug ?? null,
  );
  const [isOpen, setIsOpen] = useState(false);
  const visibleItems = items.slice(0, 8);
  const activeItem =
    (activeSlug ? items.find((item) => item.slug === activeSlug) : null) ??
    visibleItems[0] ??
    null;
  const activeChildren = activeItem?.children?.length
    ? activeItem.children.slice(0, 10)
    : [];

  return (
    <nav
      className="relative hidden border-t border-slate-100 bg-white lg:block"
      onMouseLeave={() => setIsOpen(false)}
      aria-label="Ana kategori menüsü"
    >
      <div className="mx-auto flex h-12 max-w-[1440px] items-center gap-2 px-5 xl:px-8">
        <button
          type="button"
          className="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800"
          onMouseEnter={() => {
            setIsOpen(true);
            setActiveSlug(visibleItems[0]?.slug ?? null);
          }}
          onFocus={() => setIsOpen(true)}
          onClick={() => setIsOpen((open) => !open)}
          aria-expanded={isOpen}
        >
          <Grid3X3 size={15} />
          Tüm Reyonlar
          <ChevronDown size={14} className={isOpen ? "rotate-180" : ""} />
        </button>

        <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto">
          {visibleItems.map((item) => (
            <Link
              key={item.slug}
              href={`/kategori/${item.slug}`}
              className={`inline-flex h-10 shrink-0 items-center rounded-md px-4 text-xs font-semibold transition ${
                activeItem?.slug === item.slug && isOpen
                  ? "bg-orange-50 text-orange-700"
                  : "text-slate-700 hover:bg-slate-50 hover:text-orange-600"
              }`}
              onMouseEnter={() => {
                setActiveSlug(item.slug);
                setIsOpen(true);
              }}
              onFocus={() => {
                setActiveSlug(item.slug);
                setIsOpen(true);
              }}
            >
              {item.name}
            </Link>
          ))}
        </div>
      </div>

      {isOpen && activeItem ? (
        <div
          className="absolute left-0 right-0 top-full z-50 border-t border-slate-100 bg-white shadow-md"
          onMouseEnter={() => setIsOpen(true)}
        >
          <div className="mx-auto grid max-w-[1440px] grid-cols-[280px_minmax(0,1fr)] gap-5 px-5 py-5 xl:px-8">
            <aside className="rounded-md border border-slate-200 bg-slate-50 p-3">
              {visibleItems.map((item) => (
                <Link
                  key={item.slug}
                  href={`/kategori/${item.slug}`}
                  className={`flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-medium transition ${
                    activeItem.slug === item.slug
                      ? "bg-white text-orange-700"
                      : "text-slate-700 hover:bg-white hover:text-orange-600"
                  }`}
                  onMouseEnter={() => setActiveSlug(item.slug)}
                >
                  <span className="truncate">{item.name}</span>
                  <ChevronRight size={14} className="text-slate-400" />
                </Link>
              ))}
            </aside>

            <section className="rounded-md border border-slate-200 bg-white p-5">
              <div className="mb-4 flex items-center justify-between gap-4">
                <div>
                  <h2 className="text-base font-semibold text-slate-950">
                    {activeItem.name}
                  </h2>
                </div>
                <Link
                  href={`/kategori/${activeItem.slug}`}
                  className="inline-flex h-8 items-center rounded-md border border-orange-200 px-3 text-xs font-semibold text-orange-700 hover:bg-orange-50"
                >
                  Ürünleri Gör
                </Link>
              </div>

              <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                {(activeChildren.length ? activeChildren : visibleItems).map((child) => (
                  <Link
                    key={child.slug}
                    href={`/kategori/${child.slug}`}
                    className="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:border-orange-200 hover:text-orange-600"
                  >
                    {child.name}
                  </Link>
                ))}
              </div>
            </section>
          </div>
        </div>
      ) : null}
    </nav>
  );
}
