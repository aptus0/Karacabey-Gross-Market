"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { BellRing, Heart, HelpCircle, Home, MapPin, Package, Settings, TicketPercent } from "lucide-react";

const accountLinks = [
  { label: "Hesap Özeti", href: "/account", icon: Home },
  { label: "Siparişlerim", href: "/account/orders", icon: Package },
  { label: "Adreslerim", href: "/account/addresses", icon: MapPin },
  { label: "Kuponlarım", href: "/account/coupons", icon: TicketPercent },
  { label: "Favoriler", href: "/favorites", icon: Heart },
  { label: "Bildirimler", href: "/notifications", icon: BellRing },
  { label: "Destek", href: "/account/support", icon: HelpCircle },
  { label: "Ayarlar", href: "/account/settings", icon: Settings },
];

export function AccountSidebar() {
  const pathname = usePathname();

  return (
    <aside className="account-sidebar account-sidebar--customer" aria-label="Hesap menüsü">
      <div className="account-sidebar__brand">
        <span>Müşteri Paneli</span>
        <strong>Karacabey Gross</strong>
      </div>
      {accountLinks.map(({ label, href, icon: Icon }) => {
        const active = pathname === href;
        return (
          <Link key={href} href={href} className={active ? "account-sidebar__link account-sidebar__link--active" : "account-sidebar__link"}>
            <Icon size={17} />
            {label}
          </Link>
        );
      })}
    </aside>
  );
}
