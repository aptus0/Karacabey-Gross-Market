"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { ChevronRight, LogOut, Menu, Search, ShoppingCart, X } from "lucide-react";

import { KgmLogo } from "@/app/_components/KgmLogo";
import { NavIcon } from "@/app/_components/NavIcon";
import { Button } from "@/app/_components/ui/button";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/app/_components/ui/sheet";
import { useAuthStore } from "@/lib/auth-store";
import { cartItemCount } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";
import {
  defaultCategoryMenu,
  fetchCategoryMenu,
  type CategoryMenuItem,
} from "@/lib/category-menu";

const quickLinks = [
  { label: "Kampanyalar", url: "/kampanyalar", icon: "tag" as const },
  { label: "Favoriler", url: "/favorites", icon: "heart" as const },
  { label: "Kargo Hesaplama", url: "/kargo-hesaplama", icon: "truck" as const },
  { label: "İletişim", url: "/kurumsal/iletisim", icon: "phone" as const },
];

export function MobileHeader() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState(searchParams.get("q") ?? "");
  const [categoryMenu, setCategoryMenu] =
    useState<CategoryMenuItem[]>(defaultCategoryMenu);

  const cartCount = useCartStore((state) => cartItemCount(state.items));
  const openCartSheet = useCartStore((state) => state.openSheet);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const logout = useAuthStore((state) => state.logout);

  useEffect(() => {
    const controller = new AbortController();
    fetchCategoryMenu(controller.signal)
      .then(setCategoryMenu)
      .catch(() => setCategoryMenu(defaultCategoryMenu));
    return () => controller.abort();
  }, []);

  function handleSearch(event: React.FormEvent) {
    event.preventDefault();
    const trimmedQuery = query.trim();
    router.push(trimmedQuery ? `/products?q=${encodeURIComponent(trimmedQuery)}` : "/products");
  }

  return (
    <header className="mobile-header-v17 lg:hidden">
      <div className="mobile-header-v17__bar">
        <Sheet>
          <SheetTrigger asChild>
            <button
              type="button"
              className="mobile-header-v17__icon"
              aria-label="Menüyü aç"
            >
              <Menu size={21} />
            </button>
          </SheetTrigger>
          <SheetContent
            side="left"
            className="kgm-mobile-menu-v17 flex flex-col border-r border-slate-200 bg-white p-0 sm:max-w-[380px]"
          >
            <SheetHeader className="border-b border-slate-200 px-5 py-5 text-left">
              <div className="flex items-center justify-between gap-4">
                <SheetTitle className="min-w-0">
                  <KgmLogo variant="header" compact={false} />
                </SheetTitle>
                <SheetClose asChild>
                  <button className="mobile-header-v17__icon" aria-label="Menüyü kapat">
                    <X size={19} />
                  </button>
                </SheetClose>
              </div>
              <SheetDescription className="sr-only">
                Kategoriler ve müşteri menüsü.
              </SheetDescription>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto px-4 py-5">
              <div className="mb-3 px-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Reyonlar
              </div>
              <nav className="grid gap-2">
                {categoryMenu.slice(0, 12).map((item) => (
                  <SheetClose asChild key={item.slug}>
                    <Link
                      href={`/kategori/${item.slug}`}
                      className="flex min-h-11 items-center justify-between rounded-md border border-slate-100 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-orange-100 hover:bg-orange-50 hover:text-orange-700"
                    >
                      <span>{item.name}</span>
                      <ChevronRight size={14} className="text-slate-400" />
                    </Link>
                  </SheetClose>
                ))}
              </nav>

              <div className="mb-3 mt-6 px-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Kısa yollar
              </div>
              <nav className="grid gap-2">
                {quickLinks.map((item) => (
                  <SheetClose asChild key={item.url}>
                    <Link
                      href={item.url}
                      className="flex min-h-11 items-center gap-3 rounded-md border border-slate-100 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-orange-100 hover:bg-orange-50 hover:text-orange-700"
                    >
                      <NavIcon name={item.icon} size={16} />
                      {item.label}
                    </Link>
                  </SheetClose>
                ))}
              </nav>
            </div>

            <div className="border-t border-slate-200 p-4">
              {isAuthenticated ? (
                <div className="grid gap-2">
                  <Button asChild variant="outline" className="h-10 w-full justify-start rounded-md border-slate-200 text-slate-700">
                    <Link href="/account">Kullanıcı Profili</Link>
                  </Button>
                  <Button
                    variant="outline"
                    className="h-10 w-full justify-start gap-2 rounded-md border-slate-200 text-slate-700"
                    onClick={() => {
                      logout();
                      router.push("/");
                    }}
                  >
                    <LogOut size={17} />
                    Çıkış Yap
                  </Button>
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-2">
                  <Button asChild variant="outline" className="h-10 rounded-md border-orange-200 bg-orange-50 text-orange-700">
                    <Link href="/auth/register">Kayıt Ol</Link>
                  </Button>
                  <Button asChild className="h-10 rounded-md bg-orange-600 hover:bg-orange-700">
                    <Link href="/auth/login">Giriş Yap</Link>
                  </Button>
                </div>
              )}
            </div>
          </SheetContent>
        </Sheet>

        <Link href="/" className="mobile-header-v17__logo" aria-label="Karacabey Gross Market">
          <KgmLogo variant="header" compact={false} />
        </Link>

        <button
          type="button"
          className="mobile-header-v17__cart"
          onClick={openCartSheet}
          aria-label="Sepeti aç"
        >
          <ShoppingCart size={20} />
          {cartCount > 0 && <span>{cartCount}</span>}
        </button>
      </div>

      <form className="mobile-header-v17__search" onSubmit={handleSearch}>
        <Search size={16} />
        <input
          ref={inputRef}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Ürün ara"
          aria-label="Ürün ara"
        />
        <button type="submit">Ara</button>
      </form>
    </header>
  );
}
