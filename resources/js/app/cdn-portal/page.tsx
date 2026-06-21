import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "CDN Portalı | Karacabey Gross Market",
  description: "Karacabey Gross Market CDN (Content Delivery Network) servis durumu ve yapılandırma bilgisi.",
  robots: { index: false, follow: false },
};

const REGIONS = [
  { name: "Türkiye — İstanbul",   code: "TR-IST", pop: "IST1",  hits: "—",   miss: "—",   ttl: "—",   latency: "—" },
  { name: "Türkiye — Ankara",     code: "TR-ANK", pop: "ANK1",  hits: "—",   miss: "—",   ttl: "—",   latency: "—" },
  { name: "Avrupa — Frankfurt",   code: "EU-FRA", pop: "FRA2",  hits: "—",   miss: "—",   ttl: "—",   latency: "—" },
  { name: "Avrupa — Amsterdam",   code: "EU-AMS", pop: "AMS1",  hits: "—",   miss: "—",   ttl: "—",   latency: "—" },
  { name: "Orta Doğu — Dubai",    code: "ME-DXB", pop: "DXB1",  hits: "—",   miss: "—",   ttl: "—",   latency: "—" },
];

const CDN_SERVICES = [
  { name: "Görsel Dağıtımı",      desc: "Ürün görselleri ve optimizasyon",     path: "/images/*"    },
  { name: "Statik Varlıklar",     desc: "JS, CSS, font dosyaları",             path: "/assets/*"    },
  { name: "API Önbellekleme",     desc: "Ürün & kategori endpoint önbelleği",   path: "/v1/products*" },
  { name: "Video / Medya",        desc: "Kampanya ve reklam videoları",         path: "/media/*"     },
  { name: "Dinamik İçerik",       desc: "Edge-side rendering önbelleği",        path: "/*"           },
];

const STATS = [
  { label: "Önbellekten Karşılama",  value: "—",  unit: "" },
  { label: "Toplam İstek",           value: "—",  unit: "" },
  { label: "Bant Genişliği (Bugün)", value: "—",  unit: "" },
  { label: "Aktif PoP Sayısı",       value: "0",  unit: "/ 5" },
  { label: "Ort. TTFB",              value: "—",  unit: "ms" },
  { label: "Uptime (30g)",           value: "—",  unit: "" },
];

export default function CdnPortalPage() {
  const now = new Date().toLocaleString("tr-TR", { timeZone: "Europe/Istanbul", dateStyle: "medium", timeStyle: "short" });

  return (
    <div style={{
      minHeight: "100vh",
      background: "linear-gradient(135deg, #060d1a 0%, #0c1526 60%, #060d1a 100%)",
      color: "#e2e8f0",
      fontFamily: "'JetBrains Mono', 'Fira Code', 'Consolas', monospace",
      fontSize: "14px",
    }}>

      {/* ── Top bar ── */}
      <header style={{
        borderBottom: "1px solid rgba(255,122,0,0.2)",
        background: "rgba(6,13,26,0.96)",
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
        <span style={{ color: "#1e3a5f", fontSize: "20px", fontWeight: 200 }}>/</span>
        <span style={{ color: "#94a3b8", fontSize: "13px" }}>CDN Portal</span>
        <span style={{
          marginLeft: "auto",
          background: "rgba(239,68,68,0.12)",
          border: "1px solid rgba(239,68,68,0.35)",
          color: "#fca5a5",
          borderRadius: "4px",
          padding: "2px 10px",
          fontSize: "11px",
          fontWeight: 600,
          letterSpacing: "0.08em",
          textTransform: "uppercase",
        }}>
          ● Tüm PoP'lar Çevrimdışı
        </span>
        <a href="https://karacabeygrossmarket.com" style={{ color: "#64748b", fontSize: "12px", textDecoration: "none" }}>
          ← Ana Siteye Dön
        </a>
      </header>

      <div style={{ maxWidth: "1100px", margin: "0 auto", padding: "40px 32px" }}>

        {/* ── Incident Banner ── */}
        <div style={{
          background: "rgba(239,68,68,0.07)",
          border: "1px solid rgba(239,68,68,0.3)",
          borderLeft: "4px solid #ef4444",
          borderRadius: "8px",
          padding: "20px 24px",
          marginBottom: "40px",
          display: "flex",
          gap: "16px",
          alignItems: "flex-start",
        }}>
          <span style={{ fontSize: "20px" }}>🔴</span>
          <div>
            <div style={{ fontWeight: 700, color: "#fca5a5", marginBottom: "6px", fontSize: "15px" }}>
              CDN altyapısı henüz aktif değil
            </div>
            <div style={{ color: "#94a3b8", lineHeight: 1.6 }}>
              İçerik Dağıtım Ağı (CDN) kurulumu tamamlanmamıştır. Görsel ve statik dosyalar şu an doğrudan
              ana sunucudan servis edilmektedir. Kısa süre içinde aktif hale getirilecektir.
            </div>
            <div style={{ marginTop: "10px", color: "#64748b", fontSize: "12px" }}>
              Son güncelleme: {now} (UTC+3) · Durum: <span style={{ color: "#ef4444" }}>Devre dışı — Yapılandırılıyor</span>
            </div>
          </div>
        </div>

        {/* ── Hero ── */}
        <div style={{ marginBottom: "48px" }}>
          <h1 style={{ fontSize: "32px", fontWeight: 800, color: "#f1f5f9", margin: "0 0 12px", letterSpacing: "-0.04em" }}>
            Karacabey Gross Market
            <span style={{ color: "#ff7a00" }}> CDN</span>
          </h1>
          <p style={{ color: "#64748b", lineHeight: 1.7, maxWidth: "580px", margin: 0 }}>
            İçerik Dağıtım Ağı (CDN) altyapısı: ürün görselleri, statik dosyalar ve önbellek yönetimi.
            Türkiye ve Avrupa çapında dağıtık PoP (Point of Presence) noktaları.
          </p>
        </div>

        {/* ── Stats Grid ── */}
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(180px, 1fr))", gap: "16px", marginBottom: "48px" }}>
          {STATS.map((stat) => (
            <div key={stat.label} style={{
              background: "rgba(12,21,38,0.8)",
              border: "1px solid #1e293b",
              borderRadius: "10px",
              padding: "20px",
            }}>
              <div style={{ color: "#334155", fontSize: "11px", textTransform: "uppercase", letterSpacing: "0.08em", marginBottom: "8px" }}>
                {stat.label}
              </div>
              <div style={{ fontSize: "24px", fontWeight: 800, color: "#475569" }}>
                {stat.value}
                <span style={{ fontSize: "13px", fontWeight: 400, color: "#334155", marginLeft: "4px" }}>
                  {stat.unit}
                </span>
              </div>
            </div>
          ))}
        </div>

        {/* ── PoP Status ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Nokta Durumu (PoP)
          </h2>
          <div style={{
            background: "rgba(12,21,38,0.8)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            overflow: "hidden",
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "1fr 80px 80px 80px 80px 80px 70px",
              padding: "10px 20px",
              background: "#060d1a",
              color: "#334155",
              fontSize: "11px",
              textTransform: "uppercase",
              letterSpacing: "0.08em",
              borderBottom: "1px solid #1e293b",
            }}>
              <span>Bölge</span><span>PoP</span><span>Cache Hit</span><span>Cache Miss</span><span>TTL</span><span>Gecikme</span><span style={{ textAlign: "right" }}>Durum</span>
            </div>
            {REGIONS.map((r, i) => (
              <div key={r.code} style={{
                display: "grid",
                gridTemplateColumns: "1fr 80px 80px 80px 80px 80px 70px",
                padding: "14px 20px",
                borderBottom: i < REGIONS.length - 1 ? "1px solid rgba(30,41,59,0.5)" : "none",
                alignItems: "center",
              }}>
                <span style={{ color: "#94a3b8" }}>{r.name}</span>
                <span style={{ color: "#334155", fontSize: "12px", fontFamily: "monospace" }}>{r.pop}</span>
                <span style={{ color: "#334155" }}>{r.hits}</span>
                <span style={{ color: "#334155" }}>{r.miss}</span>
                <span style={{ color: "#334155" }}>{r.ttl}</span>
                <span style={{ color: "#334155" }}>{r.latency}</span>
                <span style={{ textAlign: "right" }}>
                  <span style={{
                    background: "rgba(239,68,68,0.1)",
                    border: "1px solid rgba(239,68,68,0.25)",
                    color: "#f87171",
                    borderRadius: "4px",
                    padding: "2px 7px",
                    fontSize: "10px",
                    fontWeight: 700,
                  }}>
                    OFFLINE
                  </span>
                </span>
              </div>
            ))}
          </div>
        </section>

        {/* ── CDN Services ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            CDN Servisleri
          </h2>
          <div style={{
            background: "rgba(12,21,38,0.8)",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            overflow: "hidden",
          }}>
            {CDN_SERVICES.map((svc, i) => (
              <div key={svc.name} style={{
                display: "grid",
                gridTemplateColumns: "200px 1fr 200px 80px",
                padding: "16px 20px",
                borderBottom: i < CDN_SERVICES.length - 1 ? "1px solid rgba(30,41,59,0.5)" : "none",
                alignItems: "center",
                gap: "16px",
              }}>
                <span style={{ color: "#e2e8f0", fontWeight: 600 }}>{svc.name}</span>
                <span style={{ color: "#475569", fontSize: "12px" }}>{svc.desc}</span>
                <span style={{ color: "#334155", fontFamily: "monospace", fontSize: "12px" }}>{svc.path}</span>
                <span style={{ textAlign: "right" }}>
                  <span style={{
                    background: "rgba(239,68,68,0.1)",
                    border: "1px solid rgba(239,68,68,0.25)",
                    color: "#f87171",
                    borderRadius: "4px",
                    padding: "2px 7px",
                    fontSize: "10px",
                    fontWeight: 700,
                  }}>
                    KAPALI
                  </span>
                </span>
              </div>
            ))}
          </div>
        </section>

        {/* ── Configuration Preview ── */}
        <section style={{ marginBottom: "48px" }}>
          <h2 style={{ color: "#94a3b8", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.1em", marginBottom: "16px", fontWeight: 600 }}>
            Yapılandırma (Hazır)
          </h2>
          <div style={{
            background: "#060d1a",
            border: "1px solid #1e293b",
            borderRadius: "10px",
            padding: "24px",
            lineHeight: 2,
            overflow: "auto",
          }}>
            <div style={{ color: "#475569" }}># cdn.karacabeygrossmarket.com</div>
            <div>
              <span style={{ color: "#7dd3fc" }}>CDN_URL</span>
              <span style={{ color: "#94a3b8" }}>=</span>
              <span style={{ color: "#86efac" }}>"https://cdn.karacabeygrossmarket.com"</span>
            </div>
            <div>
              <span style={{ color: "#7dd3fc" }}>CDN_CACHE_TTL</span>
              <span style={{ color: "#94a3b8" }}>=</span>
              <span style={{ color: "#fbbf24" }}>2592000</span>
              <span style={{ color: "#475569" }}>  # 30 gün (saniye)</span>
            </div>
            <div>
              <span style={{ color: "#7dd3fc" }}>CDN_IMAGE_QUALITY</span>
              <span style={{ color: "#94a3b8" }}>=</span>
              <span style={{ color: "#fbbf24" }}>85</span>
            </div>
            <div>
              <span style={{ color: "#7dd3fc" }}>CDN_PURGE_KEY</span>
              <span style={{ color: "#94a3b8" }}>=</span>
              <span style={{ color: "#334155" }}>"*** gizli ***"</span>
            </div>
            <div style={{ marginTop: "12px", color: "#475569" }}># Durum: yapılandırma hazır, aktivasyon bekleniyor</div>
          </div>
        </section>

        {/* ── Contact ── */}
        <section style={{
          background: "rgba(255,122,0,0.05)",
          border: "1px solid rgba(255,122,0,0.2)",
          borderRadius: "10px",
          padding: "24px",
          marginBottom: "16px",
        }}>
          <h2 style={{ color: "#ff7a00", fontSize: "14px", fontWeight: 700, marginBottom: "10px" }}>
            CDN Erişimi Hakkında
          </h2>
          <p style={{ color: "#64748b", lineHeight: 1.7, margin: "0 0 12px" }}>
            CDN entegrasyonu ve kurumsal erişim talepleri için teknik ekibimizle iletişime geçebilirsiniz.
          </p>
          <a href="mailto:destek@karacabeygrossmarket.com" style={{ color: "#ff7a00", fontSize: "13px" }}>
            destek@karacabeygrossmarket.com
          </a>
        </section>

      </div>

      {/* ── Footer ── */}
      <footer style={{
        borderTop: "1px solid #1e293b",
        padding: "24px 32px",
        textAlign: "center",
        color: "#1e3a5f",
        fontSize: "12px",
      }}>
        © {new Date().getFullYear()} Karacabey Gross Market · CDN Altyapısı · Durum: Yapılandırılıyor
        {" · "}
        <a href="https://karacabeygrossmarket.com" style={{ color: "#334155", textDecoration: "none" }}>
          karacabeygrossmarket.com
        </a>
      </footer>
    </div>
  );
}
