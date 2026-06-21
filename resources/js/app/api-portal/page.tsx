import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Geliştirici API Portalı | Karacabey Gross Market",
  description: "Karacabey Gross Market API servisi — geliştirici dokümantasyonu ve servis durumu.",
  robots: { index: false, follow: false },
};

const ENDPOINTS = [
  { method: "GET",    path: "/v1/products",          desc: "Ürün listesi",           auth: false },
  { method: "GET",    path: "/v1/products/{slug}",   desc: "Ürün detayı",            auth: false },
  { method: "GET",    path: "/v1/categories",        desc: "Kategori & reyon listesi", auth: false },
  { method: "GET",    path: "/v1/search",            desc: "Ürün arama",             auth: false },
  { method: "POST",   path: "/v1/cart/items",        desc: "Sepete ekle",            auth: true  },
  { method: "GET",    path: "/v1/cart",              desc: "Sepet görüntüle",        auth: true  },
  { method: "DELETE", path: "/v1/cart/items/{id}",   desc: "Sepetten çıkar",         auth: true  },
  { method: "POST",   path: "/v1/orders",            desc: "Sipariş oluştur",        auth: true  },
  { method: "GET",    path: "/v1/orders/{id}",       desc: "Sipariş detayı",         auth: true  },
  { method: "GET",    path: "/v1/orders",            desc: "Sipariş geçmişi",        auth: true  },
  { method: "GET",    path: "/v1/campaigns",         desc: "Aktif kampanyalar",      auth: false },
  { method: "POST",   path: "/v1/auth/token",        desc: "Erişim tokeni al",       auth: false },
];

const METHOD_COLORS: Record<string, string> = {
  GET:    "#22c55e",
  POST:   "#3b82f6",
  DELETE: "#ef4444",
  PUT:    "#f59e0b",
  PATCH:  "#a78bfa",
};

const SERVICES = [
  { name: "API Gateway",        region: "TR-West",  latency: "—",    uptime: "—" },
  { name: "Auth Service",       region: "TR-West",  latency: "—",    uptime: "—" },
  { name: "Product Catalog",    region: "TR-West",  latency: "—",    uptime: "—" },
  { name: "Order Service",      region: "TR-West",  latency: "—",    uptime: "—" },
  { name: "Payment Gateway",    region: "TR-West",  latency: "—",    uptime: "—" },
  { name: "Notification Hub",   region: "TR-West",  latency: "—",    uptime: "—" },
];

export default function ApiPortalPage() {
  const now = new Date().toLocaleString("tr-TR", { timeZone: "Europe/Istanbul", dateStyle: "medium", timeStyle: "short" });

  return (
    <div style={{
      minHeight: "100vh",
      background: "linear-gradient(135deg, #0a0e1a 0%, #0f172a 60%, #0a0e1a 100%)",
      color: "#e2e8f0",
      fontFamily: "'JetBrains Mono', 'Fira Code', 'Consolas', monospace",
      fontSize: "14px",
    }}>

      {/* ── Top bar ── */}
      <header style={{
        borderBottom: "1px solid rgba(255,122,0,0.25)",
        background: "rgba(10,14,26,0.95)",
        backdropFilter: "blur(12px)",
        position: "sticky",
        top: 0,
        zIndex: 50,
        padding: "0 32px",
        display: "flex",
        alignItems: "center",
        gap: "16px",
        height: "60px",
      }}>
        <span style={{ color: "#ff7a00", fontWeight: 700, fontSize: "16px", letterSpacing: "-0.03em" }}>
          KGM
        </span>
        <span style={{ color: "#334155", fontSize: "20px", fontWeight: 200 }}>/</span>
        <span style={{ color: "#94a3b8", fontSize: "13px" }}>API Portal</span>
        <span style={{
          marginLeft: "auto",
          background: "rgba(239,68,68,0.15)",
          border: "1px solid rgba(239,68,68,0.4)",
          color: "#fca5a5",
          borderRadius: "4px",
          padding: "2px 10px",
          fontSize: "11px",
          fontWeight: 600,
          letterSpacing: "0.08em",
          textTransform: "uppercase",
        }}>
          ● Servis Dışı
        </span>
        <a href="https://karacabeygrossmarket.com" style={{ color: "#64748b", fontSize: "12px", textDecoration: "none" }}>
          ← Ana Siteye Dön
        </a>
      </header>

      <div style={{ maxWidth: "1100px", margin: "0 auto", padding: "40px 32px" }}>

        {/* ── Incident Banner ── */}
        <div style={{
          background: "rgba(239,68,68,0.08)",
          border: "1px solid rgba(239,68,68,0.35)",
          borderLeft: "4px solid #ef4444",
          borderRadius: "8px",
          padding: "20px 24px",
          marginBottom: "40px",
          display: "flex",
          gap: "16px",
          alignItems: "flex-start",
        }}>
          <span style={{ fontSize: "20px" }}>⚠️</span>
          <div>
            <div style={{ fontWeight: 700, color: "#fca5a5", marginBottom: "6px", fontSize: "15px" }}>
              API servisi şu anda kullanılamıyor
            </div>
            <div style={{ color: "#94a3b8", lineHeight: 1.6 }}>
              Karacabey Gross Market API hizmetleri yakında hizmete açılacaktır. Mevcut kullanım için lütfen
              {" "}<a href="https://karacabeygrossmarket.com" style={{ color: "#ff7a00" }}>karacabeygrossmarket.com</a> üzerinden alışveriş yapabilirsiniz.
            </div>
            <div style={{ marginTop: "10px", color: "#64748b", fontSize: "12px" }}>
              Son güncelleme: {now} (UTC+3) · Durum: <span style={{ color: "#ef4444" }}>503 Service Unavailable</span>
            </div>
          </div>
        </div>

        {/* ── Hero ── */}
        <div style={{ marginBottom: "48px" }}>
          <h1 style={{ fontSize: "32px", fontWeight: 800, color: "#f1f5f9", margin: "0 0 12px", letterSpacing: "-0.04em" }}>
            Karacabey Gross Market
            <span style={{ color: "#ff7a00" }}> API</span>
          </h1>
          <p style={{ color: "#64748b", lineHeight: 1.7, maxWidth: "580px", margin: 0 }}>
            REST tabanlı geliştirici API'si. Ürün kataloğu, sipariş yönetimi ve sepet işlemleri için
            uç noktalar. Tüm yanıtlar JSON formatındadır.
          </p>
        </div>

        {/* ── Quick Info Cards ── */}
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: "16px", marginBottom: "48px" }}>
          {[
            { label: "Base URL", value: "api.karacabeygrossmarket.com" },
            { label: "Sürüm", value: "v1 (beta)" },
            { label: "Protokol", value: "HTTPS / REST" },
            { label: "Format", value: "JSON" },
            { label: "Rate Limit", value: "120 req / dk" },
            { label: "Durum", value: "Bakımda" },
          ].map((card) => (
            <div key={card.label} style={{
              background: "rgba(15,23,42,0.7)",
              border: "1px solid #1e293b",
              borderRadius: "8px",
              padding: "16px",
            }}>
              <div style={{ color: "#475569", fontSize: "11px", textTransform: "uppercase", letterSpacing: "0.08em", marginBottom: "6px" }}>
                {card.label}
              </div>
              <div style={{ color: "#e2e8f0", fontWeight: 600 }}>{card.value}</div>
            </div>
          ))}
        </div>

        {/* ── Service Status ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Servis Durumu
          </h2>
          <div style={{
            background: "rgba(15,23,42,0.7)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            overflow: "hidden",
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "1fr 120px 80px 80px",
              padding: "10px 20px",
              background: "#0a0e1a",
              color: "#475569",
              fontSize: "11px",
              textTransform: "uppercase",
              letterSpacing: "0.08em",
              borderBottom: "1px solid #1e293b",
            }}>
              <span>Servis</span><span>Bölge</span><span>Gecikme</span><span style={{ textAlign: "right" }}>Durum</span>
            </div>
            {SERVICES.map((svc, i) => (
              <div key={svc.name} style={{
                display: "grid",
                gridTemplateColumns: "1fr 120px 80px 80px",
                padding: "14px 20px",
                borderBottom: i < SERVICES.length - 1 ? "1px solid #1e293b" : "none",
                alignItems: "center",
              }}>
                <span style={{ color: "#e2e8f0", fontWeight: 500 }}>{svc.name}</span>
                <span style={{ color: "#475569", fontSize: "12px" }}>{svc.region}</span>
                <span style={{ color: "#475569" }}>{svc.latency}</span>
                <span style={{ textAlign: "right" }}>
                  <span style={{
                    background: "rgba(239,68,68,0.12)",
                    border: "1px solid rgba(239,68,68,0.3)",
                    color: "#f87171",
                    borderRadius: "4px",
                    padding: "2px 8px",
                    fontSize: "11px",
                    fontWeight: 600,
                  }}>
                    DOWN
                  </span>
                </span>
              </div>
            ))}
          </div>
        </section>

        {/* ── Endpoints ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Uç Noktalar
          </h2>
          <div style={{
            background: "rgba(15,23,42,0.7)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            overflow: "hidden",
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "80px 1fr 200px 80px 60px",
              padding: "10px 20px",
              background: "#0a0e1a",
              color: "#475569",
              fontSize: "11px",
              textTransform: "uppercase",
              letterSpacing: "0.08em",
              borderBottom: "1px solid #1e293b",
            }}>
              <span>Metod</span><span>Yol</span><span>Açıklama</span><span>Yetki</span><span style={{ textAlign: "right" }}>HTTP</span>
            </div>
            {ENDPOINTS.map((ep, i) => (
              <div key={`${ep.method}-${ep.path}`} style={{
                display: "grid",
                gridTemplateColumns: "80px 1fr 200px 80px 60px",
                padding: "13px 20px",
                borderBottom: i < ENDPOINTS.length - 1 ? "1px solid rgba(30,41,59,0.6)" : "none",
                alignItems: "center",
              }}>
                <span style={{
                  fontWeight: 700,
                  fontSize: "11px",
                  color: METHOD_COLORS[ep.method] ?? "#94a3b8",
                }}>
                  {ep.method}
                </span>
                <span style={{ color: "#7dd3fc", fontFamily: "monospace" }}>{ep.path}</span>
                <span style={{ color: "#64748b", fontSize: "12px" }}>{ep.desc}</span>
                <span style={{ fontSize: "11px", color: ep.auth ? "#fbbf24" : "#475569" }}>
                  {ep.auth ? "Bearer" : "Herkese açık"}
                </span>
                <span style={{ textAlign: "right" }}>
                  <span style={{
                    fontSize: "11px",
                    fontWeight: 700,
                    color: "#ef4444",
                  }}>
                    503
                  </span>
                </span>
              </div>
            ))}
          </div>
        </section>

        {/* ── Auth section ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Kimlik Doğrulama
          </h2>
          <div style={{
            background: "rgba(15,23,42,0.7)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            padding: "24px",
          }}>
            <p style={{ color: "#64748b", lineHeight: 1.7, margin: "0 0 16px" }}>
              Korumalı uç noktalara erişmek için Bearer Token gereklidir.
              Tokeni <code style={{ color: "#7dd3fc", background: "rgba(125,211,252,0.08)", padding: "2px 6px", borderRadius: "4px" }}>POST /v1/auth/token</code> üzerinden alabilirsiniz.
            </p>
            <div style={{
              background: "#0a0e1a",
              border: "1px solid #1e293b",
              borderRadius: "6px",
              padding: "16px",
              color: "#94a3b8",
              lineHeight: 1.8,
            }}>
              <span style={{ color: "#475569" }}># İstek başlığı</span>{"\n"}
              <span style={{ color: "#7dd3fc" }}>Authorization</span>
              <span style={{ color: "#e2e8f0" }}>: </span>
              <span style={{ color: "#86efac" }}>Bearer {"<token>"}</span>
            </div>
            <p style={{ color: "#475569", fontSize: "12px", marginTop: "12px", marginBottom: 0 }}>
              ⚠ API erişimi için destek hattımızla iletişime geçin:{" "}
              <a href="mailto:destek@karacabeygrossmarket.com" style={{ color: "#ff7a00" }}>
                destek@karacabeygrossmarket.com
              </a>
            </p>
          </div>
        </section>

        {/* ── Rate Limits ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Hız Limitleri
          </h2>
          <div style={{
            background: "rgba(15,23,42,0.7)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            overflow: "hidden",
          }}>
            {[
              { tier: "Ücretsiz", rateMin: "60", rateDay: "1.000", burst: "10" },
              { tier: "Standart", rateMin: "120", rateDay: "10.000", burst: "30" },
              { tier: "Kurumsal", rateMin: "1.000", rateDay: "Limitsiz", burst: "200" },
            ].map((tier, i) => (
              <div key={tier.tier} style={{
                display: "grid",
                gridTemplateColumns: "160px 1fr 1fr 1fr",
                padding: "14px 20px",
                borderBottom: i < 2 ? "1px solid #1e293b" : "none",
                alignItems: "center",
              }}>
                <span style={{ color: "#e2e8f0", fontWeight: 600 }}>{tier.tier}</span>
                <span style={{ color: "#64748b", fontSize: "12px" }}>{tier.rateMin} istek/dk</span>
                <span style={{ color: "#64748b", fontSize: "12px" }}>{tier.rateDay} istek/gün</span>
                <span style={{ color: "#64748b", fontSize: "12px" }}>Burst: {tier.burst}</span>
              </div>
            ))}
          </div>
        </section>

      </div>

      {/* ── Footer ── */}
      <footer style={{
        borderTop: "1px solid #1e293b",
        padding: "24px 32px",
        textAlign: "center",
        color: "#334155",
        fontSize: "12px",
      }}>
        © {new Date().getFullYear()} Karacabey Gross Market · API v1 · Versiyon bilgisi: beta-preview
        {" · "}
        <a href="https://karacabeygrossmarket.com" style={{ color: "#475569", textDecoration: "none" }}>
          karacabeygrossmarket.com
        </a>
      </footer>
    </div>
  );
}
