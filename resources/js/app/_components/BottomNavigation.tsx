"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Grid3X3,
  Heart,
  Home,
  ShoppingCart,
  User,
} from "lucide-react";
import { cartItemCount } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";

const bottomNavItems = [
  {
    href: "/",
    label: "Ana Sayfa",
    icon: Home,
    match: (pathname: string) => pathname === "/",
  },
  {
    href: "/products",
    label: "Ürünler",
    icon: Grid3X3,
    match: (pathname: string) =>
      pathname.startsWith("/products") ||
      pathname.startsWith("/product"),
  },
  {
    href: "/checkout",
    label: "Sepet",
    icon: ShoppingCart,
    variant: "primary" as const,
    match: (pathname: string) =>
      pathname.startsWith("/checkout"),
  },
  {
    href: "/favorites",
    label: "Favoriler",
    icon: Heart,
    match: (pathname: string) => pathname.startsWith("/favorites"),
  },
  {
    href: "/account",
    label: "Profil",
    icon: User,
    match: (pathname: string) =>
      pathname.startsWith("/account") ||
      pathname.startsWith("/addresses") ||
      pathname.startsWith("/auth"),
  },
];

export function BottomNavigation() {
  const pathname = usePathname();
  const cartCount = useCartStore((state) => cartItemCount(state.items));
  const isCartOpen = useCartStore((state) => state.isSheetOpen);
  const openCartSheet = useCartStore((state) => state.openSheet);

  return (
    <nav className="bottom-nav" aria-label="Mobil alt navigasyon">
      <div className="bottom-nav__container">
        {bottomNavItems.map((item) => {
          const Icon = item.icon;
          const isActive = item.href === "/checkout" ? isCartOpen || pathname.startsWith("/checkout") : item.match(pathname);
          
          const className = `bottom-nav__item${
            isActive ? " is-active" : ""
          }${
            item.variant === "primary"
              ? " bottom-nav__item--primary"
              : ""
          }`;

          if (item.href === "/checkout") {
            return (
              <button
                key={item.href}
                type="button"
                className={className}
                onClick={openCartSheet}
              >
                <div className="bottom-nav__icon-wrapper">
                  <Icon size={20} />
                  {cartCount > 0 && (
                    <span className="bottom-nav__badge">{cartCount}</span>
                  )}
                </div>
                <span>{item.label}</span>
              </button>
            );
          }

          return (
            <Link
              key={item.href}
              href={item.href}
              className={className}
            >
              <div className="bottom-nav__icon-wrapper">
                <Icon size={20} />
              </div>
              <span>{item.label}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
