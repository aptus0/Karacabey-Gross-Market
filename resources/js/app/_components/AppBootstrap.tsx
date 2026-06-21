"use client";

import { useEffect, useRef } from "react";
import { useAuthStore } from "@/lib/auth-store";
import { useCartStore } from "@/lib/cart-store";

export function AppBootstrap() {
  const authHydrated = useAuthStore((state) => state.isHydrated);
  const authToken = useAuthStore((state) => state.token);
  const initializeAuth = useAuthStore((state) => state.initialize);
  const initializeCart = useCartStore((state) => state.initialize);
  const flushPendingCart = useCartStore((state) => state.flushPendingMutations);
  const cartStorageReady = useCartStore((state) => state.storageReady);
  const bootedRef = useRef(false);

  useEffect(() => {
    if (!authHydrated || !cartStorageReady) {
      return;
    }

    initializeAuth()
      .catch(() => undefined)
      .finally(() => {
        initializeCart()
          .then(() => flushPendingCart().catch(() => undefined))
          .catch(() => undefined);
        bootedRef.current = true;
      });
  }, [authHydrated, cartStorageReady, initializeAuth, initializeCart, flushPendingCart]);

  useEffect(() => {
    if (!authHydrated || !cartStorageReady || !bootedRef.current) {
      return;
    }

    initializeCart({ silent: true })
      .then(() => flushPendingCart().catch(() => undefined))
      .catch(() => undefined);
  }, [authHydrated, authToken, cartStorageReady, initializeCart, flushPendingCart]);

  useEffect(() => {
    const syncWhenReady = () => {
      if (document.visibilityState === "visible" && navigator.onLine !== false) {
        flushPendingCart().catch(() => undefined);
      }
    };

    window.addEventListener("online", syncWhenReady);
    document.addEventListener("visibilitychange", syncWhenReady);

    return () => {
      window.removeEventListener("online", syncWhenReady);
      document.removeEventListener("visibilitychange", syncWhenReady);
    };
  }, [flushPendingCart]);

  return null;
}
