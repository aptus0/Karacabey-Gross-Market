"use client";

import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import { ApiRequestError, apiRequest, extractErrorMessage, persistCartToken } from "@/lib/api";
import { isOfflineLikeError } from "@/lib/client-cache";
import { useAuthStore } from "@/lib/auth-store";
import {
  cartItemCount,
  emptyCart,
  normalizeCart,
  type AppliedCoupon,
  type CartData,
  type CartLineItem,
  type CartProduct,
} from "@/lib/cart";
import { productImageUrl } from "@/lib/media";

type CartStatus = "idle" | "loading" | "updating" | "error";

type CartStore = CartData & {
  status: CartStatus;
  error: string | null;
  isSheetOpen: boolean;
  isHydrated: boolean;
  storageReady: boolean;
  pendingOutboxCount: number;
  lastAddedItem: { product: CartProduct; quantity: number } | null;
  markHydrated: () => void;
  markStorageReady: () => void;
  clearLastAddedItem: () => void;
  applyRemoteCart: (cart: CartData) => void;
  flushPendingMutations: () => Promise<CartData>;
  initialize: (options?: { silent?: boolean }) => Promise<CartData>;
  addItem: (product: CartProduct, quantity?: number, options?: { openSheet?: boolean }) => Promise<CartData>;
  addItemBySlug: (slug: string, quantity?: number, options?: { openSheet?: boolean }, productId?: number) => Promise<CartData>;
  updateItemQuantity: (itemId: number, quantity: number) => Promise<CartData>;
  removeItem: (itemId: number) => Promise<CartData>;
  clearCart: () => Promise<CartData>;
  applyCoupon: (code: string) => Promise<CartData>;
  removeCoupon: () => Promise<CartData>;
  openSheet: () => void;
  closeSheet: () => void;
  count: () => number;
};

const productIdCache = new Map<string, number>();
const productCache = new Map<string, CartProduct>();
let cartTaskQueue: Promise<unknown> = Promise.resolve();
let nextOptimisticItemId = -1;

type PendingCartMutation = {
  id: string;
  type: "add" | "update" | "remove" | "clear";
  payload: Record<string, unknown>;
  attempts: number;
  createdAt: number;
};

const CART_OUTBOX_KEY = "kgm-cart-outbox-v1";
const CART_OUTBOX_MAX_SIZE = 30;

function safeNow() {
  return Date.now();
}

function createMutationId() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `web-cart-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function canUseOutboxStorage() {
  return typeof window !== "undefined" && typeof window.localStorage !== "undefined";
}

function readCartOutbox(): PendingCartMutation[] {
  if (!canUseOutboxStorage()) return [];

  try {
    const raw = window.localStorage.getItem(CART_OUTBOX_KEY);
    const parsed = raw ? JSON.parse(raw) : [];

    if (!Array.isArray(parsed)) return [];

    return parsed
      .filter((item): item is PendingCartMutation => (
        item
        && typeof item === "object"
        && typeof item.id === "string"
        && ["add", "update", "remove", "clear"].includes(item.type)
        && typeof item.payload === "object"
      ))
      .slice(-CART_OUTBOX_MAX_SIZE);
  } catch {
    return [];
  }
}

function writeCartOutbox(outbox: PendingCartMutation[]) {
  if (!canUseOutboxStorage()) return;

  try {
    window.localStorage.setItem(CART_OUTBOX_KEY, JSON.stringify(outbox.slice(-CART_OUTBOX_MAX_SIZE)));
  } catch {
    // Storage quota is not critical for checkout safety; UI keeps local optimistic cart.
  }
}

function enqueueCartMutation(type: PendingCartMutation["type"], payload: Record<string, unknown>) {
  const nextOutbox = [
    ...readCartOutbox(),
    { id: createMutationId(), type, payload, attempts: 0, createdAt: safeNow() },
  ].slice(-CART_OUTBOX_MAX_SIZE);
  writeCartOutbox(nextOutbox);

  return nextOutbox.length;
}

function removeCartOutboxHead() {
  const [, ...rest] = readCartOutbox();
  writeCartOutbox(rest);

  return rest.length;
}

function bumpCartOutboxHeadAttempt() {
  const outbox = readCartOutbox();
  const head = outbox[0];

  if (!head) return 0;

  const nextOutbox = [{ ...head, attempts: head.attempts + 1 }, ...outbox.slice(1)];
  writeCartOutbox(nextOutbox);

  return nextOutbox.length;
}

const RETRYABLE_STATUSES = new Set([0, 408, 425, 429, 500, 502, 503, 504]);
const MAX_RETRIES = 2;
const BASE_RETRY_DELAY_MS = 220;
const CART_TIMEOUT_MS = 14_000;

function shouldRetry(error: unknown): boolean {
  if (error instanceof ApiRequestError) {
    return RETRYABLE_STATUSES.has(error.status);
  }
  return isOfflineLikeError(error);
}

function isServiceUnavailable(error: unknown): boolean {
  if (error instanceof ApiRequestError) {
    return RETRYABLE_STATUSES.has(error.status);
  }
  return isOfflineLikeError(error);
}

function friendlyCartError(error: unknown, fallback: string): string {
  if (isServiceUnavailable(error)) {
    return "Sepet sunucuyla senkronize ediliyor. Ürünler cihazınızda korunuyor.";
  }
  if (error instanceof ApiRequestError && (error.status === 403 || error.status === 404)) {
    return "Sepetiniz yenilendi. Lütfen işlemi tekrar deneyin.";
  }
  return extractErrorMessage(error, fallback);
}

function shouldKeepOptimisticCart(error: unknown): boolean {
  return isServiceUnavailable(error);
}

function snapshotCart(state: CartData): CartData {
  return {
    customer_uid: state.customer_uid ?? null,
    sync_version: state.sync_version ?? 0,
    cart_token: state.cart_token ?? null,
    items: state.items.map((item) => ({ ...item, product: { ...item.product } })),
    applied_coupon: state.applied_coupon ? { ...state.applied_coupon } : null,
    subtotal_cents: state.subtotal_cents ?? 0,
    total_cents: state.total_cents ?? 0,
  };
}

function normalizeCartProduct(product: CartProduct): CartProduct {
  return {
    ...product,
    image_url: productImageUrl(product.image_url),
  };
}

function recomputeTotals(items: CartLineItem[], coupon: AppliedCoupon | null) {
  const subtotal = items.reduce((sum, item) => sum + item.line_total_cents, 0);
  let total = subtotal;
  if (coupon) {
    let discount = coupon.discount_value;
    if (coupon.discount_type === "percent") {
      discount = Math.floor((subtotal * coupon.discount_value) / 100);
    }
    if (discount > subtotal) discount = subtotal;
    total = subtotal - discount;
  }
  return { subtotal, total };
}

function applyOptimisticAdd(state: CartData, product: CartProduct, quantity: number): CartData {
  const safeProduct = normalizeCartProduct(product);
  const stockLimit = product.stock_quantity > 0 ? product.stock_quantity : 99;
  const existingIndex = state.items.findIndex((item) => item.product.id === safeProduct.id);
  const nextItems = state.items.map((item) => ({ ...item, product: { ...item.product } }));
  if (existingIndex >= 0) {
    const existing = nextItems[existingIndex];
    const nextQuantity = Math.min(existing.quantity + quantity, stockLimit, 99);
    nextItems[existingIndex] = {
      ...existing,
      quantity: nextQuantity,
      line_total_cents: nextQuantity * existing.product.price_cents,
    };
  } else {
    const optimisticId = nextOptimisticItemId;
    nextOptimisticItemId -= 1;
    const safeQuantity = Math.min(quantity, stockLimit, 99);
    nextItems.push({
      id: optimisticId,
      quantity: safeQuantity,
      line_total_cents: safeQuantity * safeProduct.price_cents,
      product: { ...safeProduct },
    });
  }
  const { subtotal, total } = recomputeTotals(nextItems, state.applied_coupon);
  return { ...state, items: nextItems, subtotal_cents: subtotal, total_cents: total };
}

function applyOptimisticUpdate(state: CartData, itemId: number, quantity: number): CartData {
  const safeQuantity = Math.max(1, Math.min(quantity, 99));
  const nextItems = state.items
    .map((item) => {
      if (item.id !== itemId) return item;
      return {
        ...item,
        quantity: safeQuantity,
        line_total_cents: safeQuantity * item.product.price_cents,
      };
    });
  const { subtotal, total } = recomputeTotals(nextItems, state.applied_coupon);
  return { ...state, items: nextItems, subtotal_cents: subtotal, total_cents: total };
}

function applyOptimisticRemove(state: CartData, itemId: number): CartData {
  const nextItems = state.items.filter((item) => item.id !== itemId);
  const { subtotal, total } = recomputeTotals(nextItems, state.applied_coupon);
  return { ...state, items: nextItems, subtotal_cents: subtotal, total_cents: total };
}

export const useCartStore = create<CartStore>()(
  persist(
    (set, get) => ({
      ...emptyCart,
      status: "idle",
      error: null,
      isSheetOpen: false,
      isHydrated: false,
      storageReady: false,
      pendingOutboxCount: readCartOutbox().length,
      lastAddedItem: null,
      markHydrated: () => set({ isHydrated: true }),
      markStorageReady: () => set({ storageReady: true }),
      clearLastAddedItem: () => set({ lastAddedItem: null }),
      applyRemoteCart: (cart) => {
        const nextCart = normalizeCart(cart);
        if (nextCart.cart_token) persistCartToken(nextCart.cart_token);
        set({ ...nextCart, status: "idle", error: null, isHydrated: true });
      },
      flushPendingMutations: async () => {
        return queueCartTask(async () => flushCartOutbox(get, set));
      },
      initialize: async (options) => {
        return queueCartTask(async () => {
          try {
            return await syncCartState(get, set, {
              silent: options?.silent,
            });
          } catch (error) {
            set({
              status: "error",
              error: friendlyCartError(error, "Sepet yüklenemedi."),
              isHydrated: true,
            });

            throw error;
          }
        });
      },
      addItem: async (product, quantity = 1, options = { openSheet: false }) => {
        const normalizedProduct = normalizeCartProduct(product);
        productCache.set(normalizedProduct.slug, normalizedProduct);
        productIdCache.set(normalizedProduct.slug, normalizedProduct.id);

        const snapshot = snapshotCart(get());
        const optimistic = applyOptimisticAdd(snapshot, normalizedProduct, quantity);
        set({
          ...optimistic,
          status: "idle",
          error: null,
          isHydrated: true,
          isSheetOpen: options.openSheet ?? false,
          lastAddedItem: { product: normalizedProduct, quantity },
        });
        notifyCartMutation();

        return queueCartTask(async () => {
          try {
            const cart = await requestCart(
              "/api/v1/cart/items",
              {
                method: "POST",
                body: JSON.stringify({ product_id: normalizedProduct.id, quantity }),
              },
              ensureGuestCartToken(get, set),
            );
            const nextCart = normalizeCart(cart);
            set({ ...nextCart, status: "idle", error: null, isHydrated: true });
            return nextCart;
          } catch (error) {
            if (shouldKeepOptimisticCart(error)) {
              const pendingOutboxCount = enqueueCartMutation("add", { product_id: normalizedProduct.id, quantity });
              const localCart = normalizeCart(get());
              set({
                ...localCart,
                status: "idle",
                error: null,
                pendingOutboxCount,
                isHydrated: true,
                isSheetOpen: options.openSheet ?? false,
                lastAddedItem: { product: normalizedProduct, quantity },
              });
              return localCart;
            }

            set({
              ...snapshot,
              status: "error",
              error: friendlyCartError(error, "Ürün sepete eklenemedi."),
              isSheetOpen: false,
              lastAddedItem: null,
            });
            throw error;
          }
        });
      },
      addItemBySlug: async (slug, quantity = 1, options = { openSheet: false }, productId) => {
        const cachedProduct = productCache.get(slug);
        if (cachedProduct && (!productId || cachedProduct.id === productId)) {
          return get().addItem(cachedProduct, quantity, options);
        }

        const snapshot = snapshotCart(get());

        return queueCartTask(async () => {
          set({ status: "updating", error: null });
          let resolvedProductId = productId ?? null;
          try {
            resolvedProductId = resolvedProductId ?? await resolveProductId(slug);
            const cart = await requestCart(
              "/api/v1/cart/items",
              {
                method: "POST",
                body: JSON.stringify({ product_id: resolvedProductId, quantity }),
              },
              ensureGuestCartToken(get, set),
            );
            const nextCart = normalizeCart(cart);
            notifyCartMutation();
            const addedItem = nextCart.items.find((item) => item.product.id === resolvedProductId);
            if (addedItem) {
              productCache.set(slug, addedItem.product);
              productIdCache.set(slug, resolvedProductId);
            }
            set({
              ...nextCart,
              status: "idle",
              error: null,
              isHydrated: true,
              isSheetOpen: options.openSheet ?? false,
              lastAddedItem: addedItem ? { product: addedItem.product, quantity } : null,
            });
            return nextCart;
          } catch (error) {
            if (shouldKeepOptimisticCart(error)) {
              const pendingOutboxCount = typeof resolvedProductId === "number"
                ? enqueueCartMutation("add", { product_id: resolvedProductId, quantity })
                : readCartOutbox().length;
              const localCart = normalizeCart(get());
              set({
                ...localCart,
                status: "idle",
                error: null,
                pendingOutboxCount,
                isHydrated: true,
                isSheetOpen: options.openSheet ?? false,
              });
              return localCart;
            }

            set({
              ...snapshot,
              status: "error",
              error: friendlyCartError(error, "Ürün sepete eklenemedi."),
            });
            throw error;
          }
        });
      },
      updateItemQuantity: async (itemId, quantity) => {
        if (quantity <= 0) {
          return get().removeItem(itemId);
        }

        const snapshot = snapshotCart(get());
        const optimistic = applyOptimisticUpdate(snapshot, itemId, quantity);
        set({ ...optimistic, status: "idle", error: null });
        notifyCartMutation();

        if (itemId < 0) {
          return normalizeCart(optimistic);
        }

        return queueCartTask(async () => {
          try {
            const cart = await requestCart(
              `/api/v1/cart/items/${itemId}`,
              {
                method: "PATCH",
                body: JSON.stringify({ quantity }),
              },
              ensureGuestCartToken(get, set),
            );
            const nextCart = normalizeCart(cart);
            set({ ...nextCart, status: "idle", error: null, isHydrated: true });
            return nextCart;
          } catch (error) {
            if (shouldKeepOptimisticCart(error)) {
              const pendingOutboxCount = enqueueCartMutation("update", { item_id: itemId, quantity });
              const localCart = normalizeCart(get());
              set({ ...localCart, status: "idle", error: null, pendingOutboxCount, isHydrated: true });
              return localCart;
            }

            if (shouldRecoverCart(error)) {
              try {
                const refreshed = await syncCartState(get, set, { silent: true });
                set({
                  ...refreshed,
                  status: "idle",
                  error: "Sepetiniz yenilendi.",
                  isHydrated: true,
                });
                return refreshed;
              } catch {
                // fall through to rollback
              }
            }
            set({
              ...snapshot,
              status: "error",
              error: friendlyCartError(error, "Sepet güncellenemedi."),
            });
            return snapshot;
          }
        });
      },
      removeItem: async (itemId) => {
        const snapshot = snapshotCart(get());
        const optimistic = applyOptimisticRemove(snapshot, itemId);
        set({ ...optimistic, status: "idle", error: null });
        notifyCartMutation();

        if (itemId < 0) {
          return normalizeCart(optimistic);
        }

        return queueCartTask(async () => {
          try {
            const cart = await requestCart(
              `/api/v1/cart/items/${itemId}`,
              { method: "DELETE" },
              ensureGuestCartToken(get, set),
            );
            const nextCart = normalizeCart(cart);
            set({ ...nextCart, status: "idle", error: null, isHydrated: true });
            return nextCart;
          } catch (error) {
            if (shouldKeepOptimisticCart(error)) {
              const pendingOutboxCount = enqueueCartMutation("remove", { item_id: itemId });
              const localCart = normalizeCart(get());
              set({ ...localCart, status: "idle", error: null, pendingOutboxCount, isHydrated: true });
              return localCart;
            }

            if (shouldRecoverCart(error)) {
              try {
                const refreshed = await syncCartState(get, set, { silent: true });
                set({ ...refreshed, status: "idle", error: null, isHydrated: true });
                return refreshed;
              } catch {
                // fall through to rollback
              }
            }
            set({
              ...snapshot,
              status: "error",
              error: friendlyCartError(error, "Ürün sepetten silinemedi."),
            });
            return snapshot;
          }
        });
      },
      clearCart: async () => {
        const snapshot = snapshotCart(get());
        set({
          ...snapshot,
          items: [],
          applied_coupon: null,
          subtotal_cents: 0,
          total_cents: 0,
          status: "idle",
          error: null,
        });
        notifyCartMutation();

        return queueCartTask(async () => {
          try {
            const cart = await requestCart(
              "/api/v1/cart",
              { method: "DELETE" },
              ensureGuestCartToken(get, set),
            );
            const nextCart = normalizeCart(cart);
            set({ ...nextCart, status: "idle", error: null, isHydrated: true });
            return nextCart;
          } catch (error) {
            if (shouldKeepOptimisticCart(error)) {
              const pendingOutboxCount = enqueueCartMutation("clear", {});
              const localCart = normalizeCart(get());
              set({ ...localCart, status: "idle", error: null, pendingOutboxCount, isHydrated: true });
              return localCart;
            }

            set({
              ...snapshot,
              status: "error",
              error: friendlyCartError(error, "Sepet temizlenemedi."),
            });
            throw error;
          }
        });
      },
      applyCoupon: async (code) => {
        return queueCartTask(async () => {
          set({ status: "updating", error: null });

          try {
            const appliedCoupon = await requestCartCoupon(code, ensureGuestCartToken(get, set));
            const nextCart = normalizeCart({
              ...get(),
              applied_coupon: appliedCoupon,
              total_cents: appliedCoupon.total_cents,
            });

            set({
              ...nextCart,
              status: "idle",
              error: null,
              isHydrated: true,
            });

            return nextCart;
          } catch (error) {
            set({
              status: "error",
              error: friendlyCartError(error, "Kupon uygulanamadı."),
            });

            throw error;
          }
        });
      },
      removeCoupon: async () => {
        return queueCartTask(async () => {
          set({ status: "updating", error: null });

          try {
            await requestCouponRemoval(ensureGuestCartToken(get, set));
            const nextCart = normalizeCart({
              ...get(),
              applied_coupon: null,
              total_cents: get().subtotal_cents,
            });

            set({
              ...nextCart,
              status: "idle",
              error: null,
              isHydrated: true,
            });

            return nextCart;
          } catch (error) {
            set({
              status: "error",
              error: friendlyCartError(error, "Kupon kaldırılamadı."),
            });

            throw error;
          }
        });
      },
      openSheet: () => set({ isSheetOpen: true }),
      closeSheet: () => set({ isSheetOpen: false }),
      count: () => cartItemCount(get().items),
    }),
    {
      name: "kgm-cart-store",
      version: 1,
      migrate: (persistedState) => persistedState,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        customer_uid: state.customer_uid,
        sync_version: state.sync_version,
        cart_token: state.cart_token,
        items: state.items,
        applied_coupon: state.applied_coupon,
        subtotal_cents: state.subtotal_cents,
        total_cents: state.total_cents,
      }),
      onRehydrateStorage: () => (state) => {
        state?.markStorageReady();
        state?.markHydrated();
      },
    },
  ),
);

function queueCartTask<T>(task: () => Promise<T>) {
  const nextTask = cartTaskQueue
    .catch(() => undefined)
    .then(task);

  cartTaskQueue = nextTask.catch(() => undefined);

  return nextTask;
}

async function flushCartOutbox(
  get: () => CartStore,
  set: (partial: Partial<CartStore>) => void,
): Promise<CartData> {
  let outbox = readCartOutbox();

  if (outbox.length === 0) {
    const cart = normalizeCart(get());
    set({ pendingOutboxCount: 0 });
    return cart;
  }

  const cartToken = ensureGuestCartToken(get, set);

  while (outbox.length > 0) {
    const mutation = outbox[0];

    try {
      if (mutation.type === "add") {
        await requestCart(
          "/api/v1/cart/items",
          {
            method: "POST",
            body: JSON.stringify({
              product_id: Number(mutation.payload.product_id),
              quantity: Number(mutation.payload.quantity ?? 1),
            }),
          },
          cartToken,
        );
      }

      if (mutation.type === "update") {
        await requestCart(
          `/api/v1/cart/items/${Number(mutation.payload.item_id)}`,
          {
            method: "PATCH",
            body: JSON.stringify({ quantity: Number(mutation.payload.quantity ?? 1) }),
          },
          cartToken,
        );
      }

      if (mutation.type === "remove") {
        await requestCart(
          `/api/v1/cart/items/${Number(mutation.payload.item_id)}`,
          { method: "DELETE" },
          cartToken,
        );
      }

      if (mutation.type === "clear") {
        await requestCart("/api/v1/cart", { method: "DELETE" }, cartToken);
      }

      const pendingOutboxCount = removeCartOutboxHead();
      set({ pendingOutboxCount });
      outbox = readCartOutbox();
    } catch (error) {
      const pendingOutboxCount = bumpCartOutboxHeadAttempt();
      const localCart = normalizeCart(get());
      set({
        ...localCart,
        status: "idle",
        error: shouldRetry(error) ? null : friendlyCartError(error, "Sepet eşitlenemedi."),
        pendingOutboxCount,
        isHydrated: true,
      });

      return localCart;
    }
  }

  return syncCartState(get, set, { silent: true });
}

async function syncCartState(
  get: () => CartStore,
  set: (partial: Partial<CartStore>) => void,
  options?: {
    silent?: boolean;
  },
) {
  const status = options?.silent ? "idle" : "loading";
  set({ status, error: null });
  const requestCartToken = ensureGuestCartToken(get, set);

  let cart: CartData;

  try {
    cart = await requestCart(
      "/api/v1/cart",
      {
        method: "GET",
      },
      requestCartToken,
    );
  } catch (error) {
    if (isServiceUnavailable(error)) {
      const fallbackCart = normalizeCart(get());

      set({
        ...fallbackCart,
        status: "idle",
        error: null,
        isHydrated: true,
      });

      return fallbackCart;
    }

    throw error;
  }

  if (!useAuthStore.getState().token && get().cart_token !== requestCartToken) {
    return normalizeCart(get());
  }

  const nextCart = normalizeCart(cart);
  if (nextCart.cart_token) persistCartToken(nextCart.cart_token);

  set({
    ...nextCart,
    status: "idle",
    error: null,
    isHydrated: true,
  });

  return nextCart;
}

function shouldRecoverCart(error: unknown) {
  return error instanceof ApiRequestError && (error.status === 403 || error.status === 404 || error.status === 503);
}

function ensureGuestCartToken(
  get: () => CartStore,
  set: (partial: Partial<CartStore>) => void,
) {
  if (useAuthStore.getState().token) {
    return null;
  }

  const existingCartToken = get().cart_token;

  if (existingCartToken) {
    return existingCartToken;
  }

  const nextCartToken = createGuestCartToken();

  set({
    cart_token: nextCartToken,
    isHydrated: true,
  });

  return nextCartToken;
}

function createGuestCartToken() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `guest-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function delay(ms: number) {
  return new Promise<void>((resolve) => setTimeout(resolve, ms));
}

async function withRetry<T>(operation: () => Promise<T>): Promise<T> {
  let attempt = 0;
  while (true) {
    try {
      return await operation();
    } catch (error) {
      if (attempt >= MAX_RETRIES || !shouldRetry(error)) {
        throw error;
      }
      const backoff = BASE_RETRY_DELAY_MS * Math.pow(2, attempt) + Math.floor(Math.random() * 120);
      attempt += 1;
      await delay(backoff);
    }
  }
}

async function requestCart(path: string, init: RequestInit, cartToken: string | null) {
  const authToken = useAuthStore.getState().token;
  return withRetry(() =>
    apiRequest<CartData>(path, {
      ...init,
      timeoutMs: CART_TIMEOUT_MS,
      cache: "no-store",
      headers: {
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...(!authToken && cartToken ? { "X-Cart-Token": cartToken } : {}),
        ...(init.headers ?? {}),
      },
    }),
  );
}

async function requestCartCoupon(code: string, cartToken: string | null) {
  const authToken = useAuthStore.getState().token;
  return withRetry(() =>
    apiRequest<AppliedCoupon>("/api/v1/cart/coupon", {
      method: "POST",
      timeoutMs: CART_TIMEOUT_MS,
      cache: "no-store",
      body: JSON.stringify({ code }),
      headers: {
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...(!authToken && cartToken ? { "X-Cart-Token": cartToken } : {}),
      },
    }),
  );
}

async function requestCouponRemoval(cartToken: string | null) {
  const authToken = useAuthStore.getState().token;
  return withRetry(() =>
    apiRequest<{ removed: boolean }>("/api/v1/cart/coupon", {
      method: "DELETE",
      timeoutMs: CART_TIMEOUT_MS,
      cache: "no-store",
      headers: {
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...(!authToken && cartToken ? { "X-Cart-Token": cartToken } : {}),
      },
    }),
  );
}

async function resolveProductId(slug: string) {
  const cachedProductId = productIdCache.get(slug);

  if (cachedProductId) {
    return cachedProductId;
  }

  const product = await apiRequest<{ id: number }>(`/api/v1/products/${encodeURIComponent(slug)}`, {
    timeoutMs: 8_000,
  });
  productIdCache.set(slug, product.id);

  return product.id;
}


function notifyCartMutation() {
  if (typeof BroadcastChannel === "undefined") return;
  const channel = new BroadcastChannel("kgm-customer-sync");
  channel.postMessage({ type: "cart_mutated", at: Date.now() });
  channel.close();
}
