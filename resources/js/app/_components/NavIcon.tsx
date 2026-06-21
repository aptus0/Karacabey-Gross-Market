"use client";

import {
  FileText,
  Grid3X3,
  Heart,
  Home,
  LogIn,
  LucideIcon,
  MapPin,
  PackageSearch,
  Phone,
  ShieldCheck,
  ShoppingCart,
  Tag,
  Truck,
  User,
} from "lucide-react";

const icons: Record<string, LucideIcon> = {
  home: Home,
  grid: Grid3X3,
  cart: ShoppingCart,
  heart: Heart,
  user: User,
  login: LogIn,
  "map-pin": MapPin,
  truck: Truck,
  "package-search": PackageSearch,
  tag: Tag,
  shield: ShieldCheck,
  phone: Phone,
  "file-text": FileText,
};

type NavIconProps = {
  name?: string | null;
  size?: number;
};

export function NavIcon({ name, size = 16 }: NavIconProps) {
  if (!name) {
    return null;
  }

  const Icon = icons[name];

  return Icon ? <Icon aria-hidden="true" size={size} strokeWidth={2.2} /> : null;
}
