import { Home, MapPin, Trash2 } from "lucide-react";
import type { UserAddress } from "@/lib/account";

type AddressCardProps = {
  address: UserAddress;
  onDelete?: (id: number) => void;
};

export function AddressCard({ address, onDelete }: AddressCardProps) {
  return (
    <article className="customer-address-card">
      <div className="customer-address-card__top">
        <span>{address.title?.toLowerCase().includes("ev") ? <Home size={19} /> : <MapPin size={19} />}</span>
        <div>
          <strong>{address.title}</strong>
          <p>{address.recipient_name}</p>
        </div>
        {address.is_default ? <em>Varsayılan</em> : null}
      </div>
      <div className="customer-address-card__body">
        <p>{address.phone}</p>
        <p>{[address.neighborhood, address.address_line].filter(Boolean).join(", ")}</p>
        <p>{[address.district, address.city, address.postal_code].filter(Boolean).join(" / ")}</p>
      </div>
      {onDelete ? (
        <button type="button" onClick={() => onDelete(address.id)} className="customer-address-card__delete">
          <Trash2 size={14} /> Sil
        </button>
      ) : null}
    </article>
  );
}
