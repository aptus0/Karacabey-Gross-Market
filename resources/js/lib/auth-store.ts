"use client";

import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import { apiRequest } from "@/lib/api";

const COOKIE_SESSION_MARKER = "cookie-session";

export type AuthUser = {
  id: number;
  public_uid?: string | null;
  customer_uid?: string | null;
  sync_version?: number;
  name: string;
  phone: string | null;
  email: string | null;
  avatar_url?: string | null;
  google_id?: string | null;
  facebook_id?: string | null;
  email_verified_at?: string | null;
  loyalty_points?: number;
  loyalty_points_lifetime?: number;
  is_vip?: boolean;
  vip_started_at?: string | null;
  vip_expires_at?: string | null;
  ad_free?: boolean;
};

type AuthState = {
  token: string | null;
  expiresAt: string | null;
  user: AuthUser | null;
  isHydrated: boolean;
  hasInitializedRemote: boolean;
  isAuthenticated: boolean;
  markHydrated: () => void;
  setSession: (token: string, user: AuthUser, expiresAt?: string | null) => void;
  clearSession: () => void;
  applyRemoteUser: (user: AuthUser | null) => void;
  initialize: () => Promise<AuthUser | null>;
  logout: () => Promise<void>;
};

function isTokenExpired(expiresAt: string | null): boolean {
  if (!expiresAt) return false;
  const ts = Date.parse(expiresAt);
  return Number.isFinite(ts) && ts <= Date.now();
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      expiresAt: null,
      user: null,
      isHydrated: false,
      hasInitializedRemote: false,
      isAuthenticated: false,
      markHydrated: () => {
        const { user } = get();
        set({ isHydrated: true, isAuthenticated: Boolean(user) });
      },
      setSession: (_token, user) =>
        set({
          // Web authentication is cookie-only. Native clients still consume
          // the bearer token returned by the same Go endpoint.
          token: COOKIE_SESSION_MARKER,
          expiresAt: null,
          user,
          isAuthenticated: true,
          isHydrated: true,
          hasInitializedRemote: true,
        }),
      clearSession: () =>
        set({
          token: null,
          expiresAt: null,
          user: null,
          isAuthenticated: false,
          isHydrated: true,
          hasInitializedRemote: true,
        }),
      applyRemoteUser: (user) => {
        if (!user) return;
        set({ user, isAuthenticated: true, isHydrated: true, hasInitializedRemote: true });
      },
      initialize: async () => {
        const { token, expiresAt } = get();

        if (token && token !== COOKIE_SESSION_MARKER && isTokenExpired(expiresAt)) {
          set({
            token: null,
            expiresAt: null,
            user: null,
            isAuthenticated: false,
            isHydrated: true,
            hasInitializedRemote: false,
          });

        }

        try {
          const user = await apiRequest<AuthUser>("/api/v1/auth/me", {
            headers: token && token !== COOKIE_SESSION_MARKER
              ? { Authorization: `Bearer ${token}` }
              : undefined,
          });

          set({
            token: token && token !== COOKIE_SESSION_MARKER ? token : COOKIE_SESSION_MARKER,
            expiresAt: token && token !== COOKIE_SESSION_MARKER ? expiresAt : null,
            user,
            isAuthenticated: true,
            isHydrated: true,
            hasInitializedRemote: true,
          });

          return user;
        } catch {
          get().clearSession();
          return null;
        }
      },
      logout: async () => {
        const token = get().token;

        try {
          await apiRequest("/api/v1/auth/logout", {
            method: "POST",
            headers: token && token !== COOKIE_SESSION_MARKER
              ? { Authorization: `Bearer ${token}` }
              : undefined,
          });
        } catch {
          // Sunucu oturumu silinemese bile yerel oturumu temizliyoruz.
        }

        get().clearSession();
      },
    }),
    {
      name: "kgm-auth-store",
      version: 2,
      migrate: (persistedState) => persistedState,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        user: state.user,
      }),
      onRehydrateStorage: () => (state) => {
        state?.markHydrated();
      },
    },
  ),
);
