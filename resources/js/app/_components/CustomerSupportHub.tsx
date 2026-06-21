import Link from "next/link";
import { HelpCircle, Mail, MapPin, MessageCircle, PackageCheck, Phone, ShieldCheck, Truck } from "lucide-react";

const supportCards = [
  {
    icon: PackageCheck,
    title: "Sipariş Desteği",
    description: "Hazırlık, teslimat ve ödeme durumu için sipariş numaranızla destek alın.",
    href: "/account/orders",
  },
  {
    icon: Truck,
    title: "Teslimat Bilgisi",
    description: "Karacabey içi teslimat saatleri, adres notları ve kurye bilgilendirmeleri.",
    href: "/sayfa/teslimat-bolgeleri",
  },
  {
    icon: ShieldCheck,
    title: "Ödeme Güvenliği",
    description: "PayTR, 3D Secure, başarısız ödeme ve iade süreçleri hakkında bilgi alın.",
    href: "/sayfa/odeme-guvenligi",
  },
];

export function CustomerSupportHub() {
  return (
    <div className="customer-support-hub">
      <section className="customer-hero-panel customer-hero-panel--support">
        <div className="customer-hero-panel__content">
          <p className="eyebrow">Destek Merkezi</p>
          <h1>Yardıma ihtiyacın olduğunda tek yerden ulaş.</h1>
          <p>
            Sipariş, teslimat, ödeme, iade ve hesap işlemleri için müşteri destek akışını sadeleştirdik.
          </p>
        </div>
        <div className="customer-support-contact">
          <span><Phone size={16} /> 0224 000 00 00</span>
          <span><Mail size={16} /> destek@karacabeygrossmarket.com</span>
          <span><MapPin size={16} /> Karacabey / Bursa</span>
        </div>
      </section>

      <section className="customer-support-grid">
        {supportCards.map((card) => {
          const Icon = card.icon;
          return (
            <Link href={card.href} key={card.title} className="customer-support-card">
              <span><Icon size={24} /></span>
              <strong>{card.title}</strong>
              <p>{card.description}</p>
            </Link>
          );
        })}
      </section>

      <section className="customer-panel-card">
        <div className="customer-panel-card__heading">
          <div>
            <p className="eyebrow">Hızlı Sorular</p>
            <h2>Sık kullanılan müşteri akışları</h2>
          </div>
          <HelpCircle size={24} />
        </div>
        <div className="customer-faq-list">
          <details>
            <summary>Siparişim mobilde ve webde aynı görünür mü?</summary>
            <p>Evet. Hesap, sepet ve sipariş durumu web/mobil arasında aynı kullanıcı kimliğiyle güncellenir.</p>
          </details>
          <details>
            <summary>Kart bilgilerim sistemde saklanıyor mu?</summary>
            <p>Hayır. Kart bilgileri PayTR güvenli ödeme ve 3D Secure ekranlarında işlenir; Karacabey Gross Market tarafında kart numarası/CVV saklanmaz.</p>
          </details>
          <details>
            <summary>Yanlış adres seçersem ne yapmalıyım?</summary>
            <p>Sipariş hazırlanmadan önce destek hattından veya sipariş detay ekranından adres notu güncellemesi talep edebilirsiniz.</p>
          </details>
        </div>
        <Link href="/notifications" className="customer-support-chat">
          <MessageCircle size={18} /> Bildirim ve sipariş güncellemelerini kontrol et
        </Link>
      </section>
    </div>
  );
}
