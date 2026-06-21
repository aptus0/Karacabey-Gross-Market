"use client";

import { Heart } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { apiRequest, extractErrorMessage } from "@/lib/api";
import { useAuthStore } from "@/lib/auth-store";
import { cn } from "@/lib/utils";

type FavoriteButtonProps = {
  productSlug: string;
  className?: string;
  label?: string;
  iconSize?: number;
};

export function FavoriteButton({ productSlug, className, label, iconSize = 16 }: FavoriteButtonProps) {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const [isFavorite, setIsFavorite] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFavoriteToggle() {
    if (!isAuthenticated || !token) {
      router.push("/auth/login");
      return;
    }

    setIsPending(true);
    setError(null);

    try {
      if (isFavorite) {
        await apiRequest(`/api/v1/favorites/${productSlug}`, {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });

        setIsFavorite(false);
      } else {
        await apiRequest(`/api/v1/favorites/${productSlug}`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });

        setIsFavorite(true);
      }
    } catch (caughtError) {
      setError(extractErrorMessage(caughtError, "Favori işlemi tamamlanamadı."));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div className="grid gap-1">
      <button
        className={cn("icon-button", isFavorite && "is-active", className)}
        type="button"
        aria-label={isFavorite ? "Favorilerden çıkar" : "Favorilere ekle"}
        title={isFavorite ? "Favorilerden çıkar" : "Favorilere ekle"}
        onClick={handleFavoriteToggle}
        disabled={isPending}
      >
        <Heart size={iconSize} fill={isFavorite ? "currentColor" : "none"} />
        {label ? <span>{isFavorite ? "Favorilerde" : label}</span> : null}
      </button>
      {error ? <span className="max-w-[140px] text-[11px] font-semibold text-[#A32A18]">{error}</span> : null}
    </div>
  );
}
