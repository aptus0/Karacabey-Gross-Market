"use client";

import { LoaderCircle, ShieldCheck } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/app/_components/ui/card";
import { apiRequest, extractErrorMessage } from "@/lib/api";
import { useAuthStore, type AuthUser } from "@/lib/auth-store";
import { useCartStore } from "@/lib/cart-store";

export default function AuthCallbackPage() {
  const router = useRouter();
  const setSession = useAuthStore((state) => state.setSession);
  const initializeCart = useCartStore((state) => state.initialize);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    async function completeAuth() {
      const hash = window.location.hash.replace(/^#/, "");
      const params = new URLSearchParams(hash);
      const token = params.get("token");
      const provider = params.get("provider");
      const callbackError = params.get("error");
      const callbackMessage = params.get("message");

      if (callbackError || !token) {
        if (active) {
          setError(callbackMessage ?? "Sosyal giriş tamamlanamadı.");
        }

        return;
      }

      try {
        const user = await apiRequest<AuthUser>("/api/v1/auth/me", {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });

        setSession(token, user);
        await initializeCart({ silent: true });
        router.replace("/account");
      } catch (caughtError) {
        if (active) {
          setError(
            extractErrorMessage(
              caughtError,
              `${provider ? `${provider} ` : ""}oturumu alınamadı.`,
            ),
          );
        }
      }
    }

    completeAuth();

    return () => {
      active = false;
    };
  }, [initializeCart, router, setSession]);

  return (
    <main className="auth-shell">
      <Card className="w-full max-w-xl">
        <CardHeader>
          <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FFF0E0] text-[#FF7A00]">
            {error ? <ShieldCheck size={22} /> : <LoaderCircle size={22} className="animate-spin" />}
          </div>
          <CardTitle>{error ? "Giriş tamamlanamadı" : "Hesabınız hazırlanıyor"}</CardTitle>
          <CardDescription>
            {error
              ? error
              : "Sosyal oturum doğrulanıyor, kullanıcı profiliniz ve sepetiniz eşitleniyor."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {error ? (
            <a
              href="/auth/login"
              className="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#FF7A00] px-5 text-sm font-black text-white"
            >
              Giriş ekranına dön
            </a>
          ) : (
            <div className="h-2 overflow-hidden rounded-full bg-[#FFF1E1]">
              <div className="h-full w-2/3 animate-pulse rounded-full bg-[#FF7A00]" />
            </div>
          )}
        </CardContent>
      </Card>
    </main>
  );
}
