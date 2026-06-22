"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { Bot, Headphones, MessageCircle, Send, X } from "lucide-react";
import { ApiRequestError } from "@/lib/api";
import { useAuthStore } from "@/lib/auth-store";
import { useCartStore } from "@/lib/cart-store";
import {
  clearStoredSupportConversation,
  createSupportConversation,
  fetchSupportMessages,
  readStoredSupportConversation,
  sendSupportMessage,
  storeSupportConversation,
  supportStreamUrl,
  type StoredSupportConversation,
  type SupportMessage,
} from "@/lib/support";

const whatsappUrl = "https://wa.me/9065453458663?text=Merhaba%2C%20Karacabey%20Gross%20Market%20sipari%C5%9Fim%20i%C3%A7in%20destek%20almak%20istiyorum.";

export function SupportWidget() {
  const [open, setOpen] = useState(false);
  const [conversation, setConversation] = useState<StoredSupportConversation | null>(null);
  const [messages, setMessages] = useState<SupportMessage[]>([]);
  const [draft, setDraft] = useState("");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const messagesRef = useRef<HTMLDivElement>(null);
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const isCartOpen = useCartStore((state) => state.isSheetOpen);
  const lastId = useMemo(() => messages.reduce((max, message) => Math.max(max, message.id), 0), [messages]);

  useEffect(() => {
    if (isCartOpen) setOpen(false);
  }, [isCartOpen]);

  useEffect(() => {
    if (user?.name) setName(user.name);
    if (user?.phone) setPhone(user.phone);
  }, [user?.name, user?.phone]);

  useEffect(() => {
    const stored = readStoredSupportConversation();
    if (!stored) return;
    setConversation(stored);
    fetchSupportMessages(stored, token)
      .then((payload) => setMessages(payload.messages ?? []))
      .catch((err) => {
        if (err instanceof ApiRequestError && (err.status === 403 || err.status === 404)) {
          clearStoredSupportConversation();
          setConversation(null);
          setMessages([]);
        }
      });
  }, [token]);

  useEffect(() => {
    if (!conversation || typeof EventSource === "undefined") return;

    const source = new EventSource(supportStreamUrl(conversation, lastId));
    source.onerror = () => {
      setError("Canlı destek bağlantısı yenileniyor. Mesajınız korunur.");
    };
    source.addEventListener("message", (event) => {
      try {
        const message = JSON.parse(event.data) as SupportMessage;
        setMessages((current) => current.some((item) => item.id === message.id) ? current : [...current, message]);
        setError(null);
      } catch {
        // Ignore malformed event payloads.
      }
    });
    return () => source.close();
  }, [conversation, lastId]);

  useEffect(() => {
    messagesRef.current?.scrollTo({ top: messagesRef.current.scrollHeight, behavior: "smooth" });
  }, [messages.length, open]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    const message = draft.trim();
    if (!message || loading) return;

    setLoading(true);
    setError(null);
    setDraft("");

    try {
      if (!conversation) {
        const created = await createSupportConversation({
          message,
          name: name.trim() || user?.name || undefined,
          phone: phone.trim() || user?.phone || undefined,
          email: user?.email ?? undefined,
          authToken: token,
        });
        const stored = { id: created.id, token: created.token };
        storeSupportConversation(stored);
        setConversation(stored);
        const payload = await fetchSupportMessages(stored, token);
        setMessages(payload.messages ?? []);
      } else {
        const sent = await sendSupportMessage(conversation, message, token);
        setMessages((current) => current.some((item) => item.id === sent.message.id) ? current : [...current, sent.message]);
      }
    } catch (err) {
      setDraft(message);
      if (conversation && err instanceof ApiRequestError && (err.status === 403 || err.status === 404)) {
        clearStoredSupportConversation();
        setConversation(null);
      }
      setError(err instanceof Error ? err.message : "Mesaj gönderilemedi.");
    } finally {
      setLoading(false);
    }
  }

  if (isCartOpen) {
    return null;
  }

  return (
    <div className={`kgm-support-float ${open ? "kgm-support-float--open" : ""}`} aria-live="polite">
      {open ? (
        <section className="kgm-support-widget" aria-label="Canlı destek">
          <div className="kgm-support-widget__head">
            <div>
              <span><Headphones size={15} /> Canlı Destek</span>
              <strong>Müşteri Hizmetleri</strong>
            </div>
            <div className="kgm-support-widget__actions">
              <a href={whatsappUrl} target="_blank" rel="noreferrer" aria-label="WhatsApp ile yaz">
                <MessageCircle size={16} />
                WhatsApp
              </a>
              <button type="button" onClick={() => setOpen(false)} aria-label="Canlı desteği kapat">
                <X size={18} />
              </button>
            </div>
          </div>

          <div ref={messagesRef} className="kgm-support-widget__messages">
            {messages.length === 0 ? (
              <div className="kgm-support-widget__empty">
                <Bot size={26} />
                <strong>Merhaba, nasıl yardımcı olalım?</strong>
                <span>AI müşteri hizmetleri ilk yanıtı verir, gerektiğinde ekibimiz devralır.</span>
              </div>
            ) : messages.map((message) => (
              <div key={message.id} className={`kgm-support-message kgm-support-message--${message.sender_type}`}>
                <small>{message.sender_name || (message.sender_type === "customer" ? "Siz" : "Destek")}</small>
                <p>{message.body}</p>
              </div>
            ))}
          </div>

          {!isAuthenticated && !conversation ? (
            <div className="kgm-support-widget__identity">
              <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Adınız" />
              <input value={phone} onChange={(event) => setPhone(event.target.value)} placeholder="Telefon" />
            </div>
          ) : null}

          {error ? <div className="kgm-support-widget__error">{error}</div> : null}

          <form className="kgm-support-widget__form" onSubmit={handleSubmit}>
            <textarea
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder="Mesajınızı yazın..."
              maxLength={1200}
              rows={2}
            />
            <button type="submit" disabled={loading || !draft.trim()} aria-label="Mesaj gönder">
              <Send size={17} />
            </button>
          </form>
        </section>
      ) : (
        <div className="kgm-support-actions">
          <a className="kgm-whatsapp-float" href={whatsappUrl} target="_blank" rel="noreferrer" aria-label="WhatsApp ile yaz">
            <MessageCircle size={18} />
            WhatsApp
          </a>
          <button type="button" className="kgm-support-launcher" onClick={() => setOpen(true)}>
            <Bot size={18} />
            AI Sohbet
          </button>
        </div>
      )}
    </div>
  );
}
