import { BellRing, Clock, CreditCard, MapPin, RotateCcw, ShieldCheck } from "lucide-react";

const trustItems = [
  {
    Icon: Clock,
    title: "Hızlı Teslimat",
    description: "Karacabey içi planlı rota",
    color: "trust-bar__item--orange",
  },
  {
    Icon: CreditCard,
    title: "Güvenli Ödeme",
    description: "PayTR + 3D Secure",
    color: "trust-bar__item--orange",
  },
  {
    Icon: MapPin,
    title: "Yerel Stok",
    description: "Güncel stok bilgisi",
    color: "trust-bar__item--orange",
  },
  {
    Icon: BellRing,
    title: "Anlık Bildirim",
    description: "Sipariş ve kampanya takibi",
    color: "trust-bar__item--orange",
  },
  {
    Icon: RotateCcw,
    title: "Kolay İade",
    description: "Net ve hızlı destek akışı",
    color: "trust-bar__item--orange",
  },
  {
    Icon: ShieldCheck,
    title: "Tek Hesap",
    description: "Aynı hesap deneyimi",
    color: "trust-bar__item--orange",
  },
];

export function TrustBar() {
  return (
    <section className="trust-bar-wrapper trust-bar-wrapper--phase6" aria-label="Alışveriş güvencelerimiz">
      <div className="content-band trust-bar-band">
        <ul className="trust-bar" role="list">
          {trustItems.map((item) => {
            const Icon = item.Icon;
            return (
              <li key={item.title} className={`trust-bar__item ${item.color}`}>
                <span className="trust-bar__icon" aria-hidden="true">
                  <Icon size={20} />
                </span>
                <span className="trust-bar__copy">
                  <strong>{item.title}</strong>
                  <small>{item.description}</small>
                </span>
              </li>
            );
          })}
        </ul>
      </div>
    </section>
  );
}
