"use client";

import { useEffect, useRef } from "react";
import { apiRequest } from "@/lib/api";
import { useAuthStore, type AuthUser } from "@/lib/auth-store";
import { useCartStore } from "@/lib/cart-store";
import type { CartData } from "@/lib/cart";

type CustomerSnapshot = {
  identity: {
    customer_uid: string;
    session_uid: string;
    source: string;
  };
  user?: AuthUser | null;
  cart?: CartData | null;
  sync_version: number;
  server_at: string;
};

type CustomerSyncState = {
  identity: {
    customer_uid: string;
    session_uid: string;
    source: string;
  };
  client_version: number;
  server_version: number;
  has_changes: boolean;
  reason?: string | null;
  updated_at?: string | null;
  server_at: string;
};

const SYNC_INTERVAL_MS = Math.max(
  5_000,
  Number(process.env.NEXT_PUBLIC_CUSTOMER_SYNC_SECONDS ?? "12") * 1_000,
);

export function CustomerSyncBridge() {
  const token = useAuthStore((state) => state.token);
  const applyRemoteUser = useAuthStore((state) => state.applyRemoteUser);
  const cartToken = useCartStore((state) => state.cart_token);
  const applyRemoteCart = useCartStore((state) => state.applyRemoteCart);
  const lastVersionRef = useRef(0);
  const runningRef = useRef(false);
  const forceSnapshotRef = useRef(false);
  const nextSyncAttemptRef = useRef(0);
  const failureCountRef = useRef(0);

  useEffect(() => {
    const channel = typeof BroadcastChannel !== "undefined" ? new BroadcastChannel("kgm-customer-sync") : null;

    async function pullSnapshot(reason: string) {
      const snapshot = await apiRequest<CustomerSnapshot>(`/api/v1/customer/snapshot?reason=${encodeURIComponent(reason)}`, {
        timeoutMs: 6_000,
        cache: "no-store",
        headers: {
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(!token && cartToken ? { "X-Cart-Token": cartToken } : {}),
        },
      });

      if (snapshot.sync_version && snapshot.sync_version < lastVersionRef.current) return;
      lastVersionRef.current = snapshot.sync_version ?? lastVersionRef.current;

      if (snapshot.user) applyRemoteUser(snapshot.user);
      if (snapshot.cart) applyRemoteCart(snapshot.cart);

      channel?.postMessage({ type: "snapshot_applied", version: snapshot.sync_version });
    }

    async function syncCustomerState(reason: string) {
      if (runningRef.current) return;
      if (Date.now() < nextSyncAttemptRef.current) return;
      if (!token && !cartToken && reason !== "boot" && reason !== "focus" && reason !== "visible") return;
      runningRef.current = true;

      try {
        const state = await apiRequest<CustomerSyncState>(`/api/v1/customer/sync-state?since=${lastVersionRef.current}&reason=${encodeURIComponent(reason)}`, {
          timeoutMs: 4_000,
          cache: "no-store",
          headers: {
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(!token && cartToken ? { "X-Cart-Token": cartToken } : {}),
          },
        });

        const shouldPullSnapshot = forceSnapshotRef.current || state.has_changes || lastVersionRef.current === 0;
        forceSnapshotRef.current = false;

        if (!shouldPullSnapshot) {
          failureCountRef.current = 0;
          nextSyncAttemptRef.current = 0;
          return;
        }
        await pullSnapshot(reason);
        failureCountRef.current = 0;
        nextSyncAttemptRef.current = 0;
      } catch {
        failureCountRef.current = Math.min(failureCountRef.current + 1, 4);
        nextSyncAttemptRef.current = Date.now() + Math.min(7_500 * failureCountRef.current, 30_000);
        // Sessiz çalışır; müşteri deneyimini bozmaz.
      } finally {
        runningRef.current = false;
      }
    }

    syncCustomerState("boot");

    const interval = window.setInterval(() => syncCustomerState("interval"), SYNC_INTERVAL_MS);
    const onFocus = () => syncCustomerState("focus");
    const onVisibility = () => {
      if (document.visibilityState === "visible") syncCustomerState("visible");
    };

    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisibility);
    channel?.addEventListener("message", (event) => {
      if (event.data?.type === "cart_mutated" || event.data?.type === "profile_mutated") {
        forceSnapshotRef.current = true;
        syncCustomerState(event.data.type);
      }
    });

    return () => {
      window.clearInterval(interval);
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisibility);
      channel?.close();
    };
  }, [applyRemoteCart, applyRemoteUser, cartToken, token]);

  return null;
}

export function notifyCustomerMutation(type: "cart_mutated" | "profile_mutated") {
  if (typeof BroadcastChannel === "undefined") return;
  const channel = new BroadcastChannel("kgm-customer-sync");
  channel.postMessage({ type, at: Date.now() });
  channel.close();
}
