"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Loader2, MapPinOff, Plus } from "lucide-react";
import { AddressCard } from "@/app/_components/AddressCard";
import { AddressMapPicker } from "@/app/_components/AddressMapPicker";
import { deleteUserAddress, fetchUserAddresses, type UserAddress } from "@/lib/account";
import { useAuthStore } from "@/lib/auth-store";

export function AccountAddresses() {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const [addresses, setAddresses] = useState<UserAddress[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!token) {
      router.replace("/auth/login");
      return;
    }

    fetchUserAddresses(token)
      .then(setAddresses)
      .catch(() => setError("Adresler yüklenemedi."))
      .finally(() => setLoading(false));
  }, [token, isHydrated, router]);

  async function handleDelete(id: number) {
    if (!token) return;
    try {
      await deleteUserAddress(token, id);
      setAddresses((prev) => prev.filter((a) => a.id !== id));
    } catch {
      setError("Adres silinemedi.");
    }
  }

  if (loading) {
    return (
      <div className="customer-empty-state">
        <Loader2 size={22} className="animate-spin" />
        Adresler yükleniyor...
      </div>
    );
  }

  if (error) return <p className="py-4 text-sm text-[#DC2626]">{error}</p>;

  if (addresses.length === 0) {
    return (
      <div className="customer-empty-state customer-empty-state--address">
        <MapPinOff size={32} />
        <strong>Kayıtlı adresiniz bulunmuyor.</strong>
        <p>Checkout veya adres ekranından Google Maps konum desteğiyle adres ekleyebilirsiniz.</p>
        <AddressMapPicker compact />
      </div>
    );
  }

  return (
    <div className="customer-address-experience">
      <button type="button" className="customer-address-add-card">
        <Plus size={22} />
        <strong>Yeni adres ekle</strong>
        <span>Google Maps konum desteğiyle checkout süresini kısalt.</span>
      </button>
      <AddressMapPicker compact />
      {addresses.map((address) => <AddressCard key={address.id} address={address} onDelete={handleDelete} />)}
    </div>
  );
}
