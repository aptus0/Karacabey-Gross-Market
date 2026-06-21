"use client";

import Link from "next/link";
import { Bell, BellRing, ChevronRight, Loader2 } from "lucide-react";
import { useEffect, useState } from "react";
import { useAuthStore } from "@/lib/auth-store";
import {
  fetchUserNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type StorefrontNotificationItem,
} from "@/lib/notifications";

export function NotificationsCenter() {
  const token = useAuthStore((state) => state.token);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const [notifications, setNotifications] = useState<StorefrontNotificationItem[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!isHydrated) return;

    if (!isAuthenticated || !token) {
      setNotifications([]);
      setUnreadCount(0);
      setLoading(false);
      return;
    }

    setLoading(true);
    fetchUserNotifications(token, 50)
      .then((payload) => {
        setNotifications(payload.data ?? []);
        setUnreadCount(payload.meta?.unread_count ?? 0);
      })
      .catch(() => {
        setNotifications([]);
        setUnreadCount(0);
      })
      .finally(() => setLoading(false));
  }, [isAuthenticated, isHydrated, token]);

  async function handleMarkAllRead() {
    if (!token) return;
    await markAllNotificationsRead(token);
    setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at ?? new Date().toISOString() })));
    setUnreadCount(0);
  }

  async function handleMarkRead(notificationId: string) {
    if (!token) return;
    const updated = await markNotificationRead(token, notificationId);
    setNotifications((current) => current.map((item) => (item.id === notificationId ? updated : item)));
    setUnreadCount((current) => Math.max(current - 1, 0));
  }

  if (!isHydrated) return null;

  if (!isAuthenticated || !token) {
    return (
      <section className="notification-center notification-center--premium">
        <div className="notification-center__hero">
          <BellRing size={28} />
          <div>
            <p className="eyebrow">Bildirim Merkezi</p>
            <h1>Hesabınıza giriş yapın</h1>
            <p>Yeni kampanyaları, ürün güncellemelerini ve sipariş akışını tek ekranda görün.</p>
          </div>
        </div>
        <Link href="/auth/login" className="primary-action">Giriş Yap</Link>
      </section>
    );
  }

  return (
    <section className="notification-center notification-center--premium">
      <div className="notification-center__hero notification-center__hero--orange">
        <div className="notification-center__icon"><BellRing size={26} /></div>
        <div className="notification-center__hero-copy">
          <p className="eyebrow">Bildirim Merkezi</p>
          <h1>Bildirimleriniz hazır</h1>
          <p>{unreadCount > 0 ? `${unreadCount} okunmamış bildirim var.` : "Tüm bildirimler okundu."}</p>
        </div>
        <div className="notification-center__actions">
          {unreadCount > 0 ? (
            <button type="button" className="secondary-action" onClick={() => handleMarkAllRead().catch(() => undefined)}>
              Tümünü Okundu Yap
            </button>
          ) : null}
        </div>
      </div>

      {loading ? (
        <div className="customer-empty-state"><Loader2 size={22} className="animate-spin" /> Bildirimler yükleniyor...</div>
      ) : notifications.length === 0 ? (
        <div className="customer-empty-state"><Bell size={22} /><strong>Henüz bildirim yok.</strong><p>Sipariş ve kampanya bildirimleri burada listelenir.</p></div>
      ) : (
        <div className="notification-center__list">
          {notifications.map((notification) => {
            const unread = !notification.read_at;
            return (
              <article key={notification.id} className={`notification-card${unread ? " notification-card--unread" : ""}`}>
                {notification.image_url ? <img src={notification.image_url} alt={notification.title} className="notification-card__image" /> : null}
                <div className="notification-card__content">
                  <div className="notification-card__meta">
                    <span>{notification.type}</span>
                    <span>{formatDateLabel(notification.created_at)}</span>
                  </div>
                  <h2>{notification.title}</h2>
                  <p>{notification.body}</p>
                  <div className="notification-card__footer">
                    {notification.action_url ? (
                      <Link href={notification.action_url} className="notification-card__link" onClick={() => unread ? handleMarkRead(notification.id).catch(() => undefined) : undefined}>
                        İçeriği Aç <ChevronRight size={14} />
                      </Link>
                    ) : <span className="notification-card__hint">Bilgi bildirimi</span>}
                    {unread ? <button type="button" className="notification-card__mark" onClick={() => handleMarkRead(notification.id).catch(() => undefined)}>Okundu</button> : null}
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}

function formatDateLabel(value?: string | null) {
  if (!value) return "Yeni";
  return new Intl.DateTimeFormat("tr-TR", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }).format(new Date(value));
}
