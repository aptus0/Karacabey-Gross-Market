"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { Heart, Loader2, ShoppingCart } from "lucide-react";
import { fetchUserFavorites, formatCartMoney, type FavoriteProduct } from "@/lib/account";
import { useAuthStore } from "@/lib/auth-store";

export function FavoritesList() {
  const token = useAuthStore((state) => state.token);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const [favorites, setFavorites] = useState<FavoriteProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!token) {
      setLoading(false);
      return;
    }

    fetchUserFavorites(token)
      .then(setFavorites)
      .catch(() => setError("Favoriler yüklenemedi."))
      .finally(() => setLoading(false));
  }, [token, isHydrated]);

  if (loading) {
    return (
      <div className="customer-empty-state">
        <Loader2 size={22} className="animate-spin" />
        Favoriler yükleniyor...
      </div>
    );
  }

  if (error) return <p className="py-4 text-sm text-[#DC2626]">{error}</p>;

  if (favorites.length === 0) {
    return (
      <div className="customer-empty-state">
        <Heart size={36} />
        <strong>Favori ürününüz bulunmuyor.</strong>
        <p>Tekrar alacağınız ürünleri favorileyerek web ve mobilde hazır liste oluşturabilirsiniz.</p>
        <Link href="/products" className="secondary-action text-xs">Ürünlere Gözat</Link>
      </div>
    );
  }

  return (
    <div className="customer-favorite-grid">
      {favorites.map((product) => (
        <article key={product.id} className="customer-favorite-card">
          <Link href={`/product/${product.slug}`} className="customer-favorite-card__image">
            {product.image_url ? (
              <Image src={product.image_url} alt={product.name} fill sizes="(max-width: 640px) 50vw, 280px" className="object-cover transition group-hover:scale-105" />
            ) : (
              <div>KGM</div>
            )}
          </Link>
          <div className="customer-favorite-card__body">
            <p>{product.brand ?? "Karacabey Gross Market"}</p>
            <Link href={`/product/${product.slug}`}>{product.name}</Link>
            <strong>{formatCartMoney(product.price_cents)}</strong>
          </div>
          <button type="button" className="customer-favorite-card__cart">
            <ShoppingCart size={15} /> Sepete Ekle
          </button>
        </article>
      ))}
    </div>
  );
}
