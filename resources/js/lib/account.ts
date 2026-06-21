"use client";

import { apiRequest } from "@/lib/api";
import { formatCartMoney } from "@/lib/cart";

export type UserAddress = {
  id: number;
  title: string;
  recipient_name: string;
  phone: string;
  city: string;
  district: string;
  neighborhood?: string | null;
  address_line: string;
  postal_code?: string | null;
  is_default: boolean;
};

export type UserOrderItem = {
  id: number;
  name: string;
  quantity: number;
  unit_price_cents: number;
  line_total_cents: number;
};

export type UserOrder = {
  id: number;
  merchant_oid: string;
  checkout_ref: string;
  status: string;
  status_label: string;
  currency: string;
  subtotal_cents: number;
  shipping_cents: number;
  discount_cents: number;
  total_cents: number;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  shipping_city?: string | null;
  shipping_district?: string | null;
  shipping_address: string;
  paid_at?: string | null;
  created_at: string;
  items: UserOrderItem[];
};

export type FavoriteProduct = {
  id: number;
  name: string;
  slug: string;
  brand?: string | null;
  price_cents: number;
  price: string;
  image_url?: string | null;
};

export type CustomerCoupon = {
  id: number;
  code: string;
  discount_type: "fixed" | "percent" | string;
  discount_value: number;
  minimum_order_cents: number;
  starts_at?: string | null;
  ends_at?: string | null;
  is_active: boolean;
  usage_limit?: number | null;
  used_count: number;
};

export type CustomerDashboard = {
  summary: {
    orders_total: number;
    orders_active: number;
    addresses_total: number;
    favorites_total: number;
    unread_notifications: number;
    cart_items_count: number;
    cart_total_cents: number;
  };
  identity: {
    customer_uid: string;
    session_uid: string;
    source: string;
  };
  sync: {
    version: number;
    reason?: string | null;
    updated_at?: string | null;
  };
  recent_orders: UserOrder[];
  quick_actions: { label: string; href: string; kind: string }[];
  server_at: string;
};

type PaginatedResponse<T> = {
  data: T[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
};

function authHeaders(token: string) {
  return { Authorization: `Bearer ${token}` };
}

function normalizeOrders(payload: PaginatedResponse<UserOrder>): PaginatedResponse<UserOrder> {
  return {
    ...payload,
    data: (payload.data ?? []).map((order) => ({
      ...order,
      checkout_ref: order.checkout_ref ?? "",
      status_label: order.status_label ?? order.status,
      currency: order.currency ?? "TL",
      subtotal_cents: order.subtotal_cents ?? order.total_cents ?? 0,
      shipping_cents: order.shipping_cents ?? 0,
      discount_cents: order.discount_cents ?? 0,
      total_cents: order.total_cents ?? 0,
      customer_name: order.customer_name ?? "",
      customer_email: order.customer_email ?? "",
      customer_phone: order.customer_phone ?? "",
      shipping_address: order.shipping_address ?? "",
      items: Array.isArray(order.items) ? order.items : [],
    })),
  };
}

export async function fetchCustomerDashboard(token: string): Promise<CustomerDashboard> {
  return apiRequest<CustomerDashboard>("/api/v1/customer/dashboard", {
    headers: authHeaders(token),
    cache: "no-store",
  });
}

export async function fetchUserOrders(token: string): Promise<PaginatedResponse<UserOrder>> {
  const res = await apiRequest<PaginatedResponse<UserOrder>>("/api/v1/orders", {
    headers: authHeaders(token),
  });
  return normalizeOrders(res);
}

export async function fetchUserOrder(token: string, orderId: number): Promise<UserOrder> {
  const order = await apiRequest<UserOrder>(`/api/v1/orders/${orderId}`, {
    headers: authHeaders(token),
  });
  return normalizeOrders({ data: [order], total: 1, per_page: 1, current_page: 1, last_page: 1 }).data[0];
}

export async function fetchUserAddresses(token: string): Promise<UserAddress[]> {
  const res = await apiRequest<UserAddress[]>("/api/v1/addresses", {
    headers: authHeaders(token),
  });
  return Array.isArray(res) ? res : (res as { data?: UserAddress[] }).data ?? [];
}

export async function deleteUserAddress(token: string, addressId: number): Promise<void> {
  await apiRequest(`/api/v1/addresses/${addressId}`, {
    method: "DELETE",
    headers: authHeaders(token),
  });
}

export async function fetchUserFavorites(token: string): Promise<FavoriteProduct[]> {
  const res = await apiRequest<FavoriteProduct[]>("/api/v1/favorites", {
    headers: authHeaders(token),
  });
  return Array.isArray(res) ? res : (res as { data?: FavoriteProduct[] }).data ?? [];
}

export async function fetchCustomerCoupons(token: string): Promise<CustomerCoupon[]> {
  const res = await apiRequest<CustomerCoupon[]>("/api/v1/customer/coupons", {
    headers: authHeaders(token),
  });
  return Array.isArray(res) ? res : (res as { data?: CustomerCoupon[] }).data ?? [];
}

export function orderStatusColor(status: string): string {
  switch (status) {
    case "paid":
    case "completed":
    case "delivered":
      return "text-[#16A34A]";
    case "awaiting_payment":
    case "preparing":
    case "shipping":
    case "in_delivery":
      return "text-[#D97706]";
    case "failed":
    case "cancelled":
      return "text-[#DC2626]";
    case "refunded":
      return "text-[#6B7177]";
    default:
      return "text-[#2B2F36]";
  }
}

export function orderProgress(status: string): number {
  switch (status) {
    case "awaiting_payment":
      return 20;
    case "paid":
      return 40;
    case "preparing":
      return 60;
    case "shipping":
    case "in_delivery":
      return 82;
    case "completed":
    case "delivered":
      return 100;
    case "failed":
    case "cancelled":
      return 100;
    default:
      return 25;
  }
}

export function formatOrderDate(iso: string): string {
  return new Intl.DateTimeFormat("tr-TR", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date(iso));
}

export function formatDateTime(iso?: string | null): string {
  if (!iso) return "Yeni";
  return new Intl.DateTimeFormat("tr-TR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

export { formatCartMoney };
