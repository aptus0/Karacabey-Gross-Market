"use client";

import Link from "next/link";
import { Bell } from "lucide-react";
import { useEffect, useState } from "react";
import { useAuthStore } from "@/lib/auth-store";
import { fetchUserNotifications } from "@/lib/notifications";

type NotificationBellProps = {
  mobile?: boolean;
  onNavigate?: () => void;
};

export function NotificationBell({ mobile = false, onNavigate }: NotificationBellProps) {
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const token = useAuthStore((state) => state.token);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    if (!isHydrated || !isAuthenticated || !token) {
      setUnreadCount(0);
      return;
    }

    let cancelled = false;

    const load = async () => {
      try {
        const payload = await fetchUserNotifications(token, 10);

        if (!cancelled) {
          setUnreadCount(payload.meta?.unread_count ?? 0);
        }
      } catch {
        if (!cancelled) {
          setUnreadCount(0);
        }
      }
    };

    load().catch(() => undefined);
    const interval = window.setInterval(() => {
      load().catch(() => undefined);
    }, 60000);

    return () => {
      cancelled = true;
      window.clearInterval(interval);
    };
  }, [isAuthenticated, isHydrated, token]);

  const href = isAuthenticated ? "/notifications" : "/auth/login";

  if (mobile) {
    return (
      <Link
        href={href}
        className="mobile-header__notification"
        aria-label={isAuthenticated ? "Bildirimler" : "Giris yap"}
        onClick={onNavigate}
      >
        <Bell size={16} />
        <span>Bildirim</span>
        {unreadCount > 0 ? <small>{unreadCount > 99 ? "99+" : unreadCount}</small> : null}
      </Link>
    );
  }

  return (
    <Link
      href={href}
      className="header-action header-action--ghost header-action--desktop-only header-action--notification"
      aria-label={isAuthenticated ? "Bildirimler" : "Giris yap"}
      onClick={onNavigate}
    >
      <Bell size={20} />
      {unreadCount > 0 ? <small>{unreadCount > 99 ? "99+" : unreadCount}</small> : null}
    </Link>
  );
}
