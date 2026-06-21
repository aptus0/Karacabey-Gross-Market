"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Check, Eye, EyeOff, Loader2, LockKeyhole, Phone, ShieldCheck, User } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { ApiRequestError, apiRequest, extractErrorMessage } from "@/lib/api";
import { useAuthStore, type AuthUser } from "@/lib/auth-store";
import { useCartStore } from "@/lib/cart-store";

const phoneSchema = z
  .string()
  .trim()
  .min(10, "Telefon numarasını kontrol edin.")
  .max(20, "Telefon numarası çok uzun.")
  .regex(/^[0-9+\s\-()]+$/, "Yalnızca telefon numarası girin.");

const loginSchema = z.object({
  phone: phoneSchema,
  password: z.string().min(1, "Şifrenizi girin."),
});

const registerSchema = z.object({
  name: z.string().trim().min(2, "Ad soyad girin.").max(120),
  phone: phoneSchema,
  password: z.string().min(8, "Şifre en az 8 karakter olmalı."),
});

type AuthMode = "login" | "register";
type AuthFormValues = { name?: string; phone: string; password: string };
type AuthResponse = {
  user: AuthUser;
  token: string;
  token_type?: string;
  expires_at?: string | null;
};

function normalizePhone(phone: string) {
  // Remove all non-digit characters
  const digits = phone.replace(/\D/g, "");
  
  // Remove country code if present (+90 prefix)
  if (digits.startsWith("90")) {
    return digits.slice(2);
  }
  
  // Drop leading trunk zero (05xx -> 5xx)
  if (digits.startsWith("0")) {
    return digits.slice(1);
  }
  
  return digits;
}

function isSensitiveParam(key: string) {
  return ["password", "phone", "email", "token", "secret", "code"].includes(key.toLowerCase());
}

export function AuthExperience({ mode }: { mode: AuthMode }) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const setSession = useAuthStore((s) => s.setSession);
  const initializeCart = useCartStore((s) => s.initialize);
  const cartToken = useCartStore((s) => s.cart_token);

  const [showPassword, setShowPassword] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [successState, setSuccessState] = useState(false);
  const schema = useMemo(() => (mode === "login" ? loginSchema : registerSchema), [mode]);

  const { formState: { errors, isSubmitting }, handleSubmit, register } = useForm<AuthFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", phone: "", password: "" },
  });

  useEffect(() => {
    if (isAuthenticated) router.replace("/account");
  }, [isAuthenticated, router]);

  useEffect(() => {
    if (Array.from(searchParams.keys()).some(isSensitiveParam)) {
      router.replace(pathname, { scroll: false });
    }
  }, [pathname, router, searchParams]);

  async function submit(values: AuthFormValues) {
    setFormError(null);

    if (typeof navigator !== "undefined" && navigator.onLine === false) {
      setFormError("Bağlantı yok.");
      return;
    }

    try {
      const normalizedPhone = normalizePhone(values.phone);
      
      const payload = await apiRequest<AuthResponse>(
        mode === "login" ? "/api/v1/auth/login" : "/api/v1/auth/register",
        {
          timeoutMs: 8_000,
          method: "POST",
          body: JSON.stringify({
            name: values.name?.trim(),
            phone: normalizedPhone,
            password: values.password,
            device_name: "next-storefront",
            cart_token: cartToken ?? undefined,
          }),
        },
      );

      setSuccessState(true);
      setSession(payload.token, payload.user, payload.expires_at ?? null);
      void initializeCart({ silent: true }).catch(() => undefined);
      router.replace("/account");
    } catch (error: unknown) {
      if (error instanceof ApiRequestError) {
        console.error("[AUTH] API Error:", {
          status: error.status,
          message: error.message,
          errors: error.errors,
          payload: error.payload,
        });
        setFormError(error.message || (mode === "login" ? "Giriş yapılamadı." : "Kayıt tamamlanamadı."));
        return;
      }

      console.error("[AUTH] Unknown error:", error);
      setFormError(extractErrorMessage(error, mode === "login" ? "Giriş yapılamadı." : "Kayıt tamamlanamadı."));
    }
  }

  return (
    <div className="auth-card auth-card--minimal" aria-busy={isSubmitting || successState}>
      {(isSubmitting || successState) ? (
        <div className="auth-card__loading" role="status" aria-live="polite">
          {successState ? <Check size={18} /> : <Loader2 size={18} className="animate-spin" />}
          <span>{successState ? "Oturum açıldı, yönlendiriliyorsunuz." : "Bilgiler güvenli şekilde kontrol ediliyor."}</span>
        </div>
      ) : null}

      <Link href="/" className="auth-card__logo" aria-label="Ana sayfa">
        <KgmLogo variant="header" />
      </Link>

      <div className="auth-card__header">
        <span className="auth-card__badge"><ShieldCheck size={13} /> Güvenli hesap</span>
        <h1 className="auth-card__title">{mode === "login" ? "Hesabınıza giriş yapın" : "Yeni hesap oluşturun"}</h1>
        <p className="auth-card__sub">
          {mode === "login"
            ? "Telefon numaranız ve şifrenizle sepetinize ve adreslerinize hızlıca ulaşın."
            : "Sipariş, adres ve hızlı checkout deneyimi için bilgilerinizi güvenli şekilde kaydedin."}
        </p>
      </div>

      <form className="auth-form" onSubmit={handleSubmit(submit)} noValidate>
        <fieldset className="auth-form__fieldset" disabled={isSubmitting || successState}>
          {mode === "register" ? (
            <div className="auth-field">
              <label htmlFor="name">Ad Soyad</label>
              <div className="auth-field__input-wrap">
                <User size={15} className="auth-field__icon" />
                <input id="name" type="text" autoComplete="name" placeholder="Ad Soyad" {...register("name")} />
              </div>
              {"name" in errors && errors.name ? <span className="auth-field__error">{errors.name.message}</span> : null}
            </div>
          ) : null}

          <div className="auth-field">
            <label htmlFor="phone">Telefon</label>
            <div className="auth-field__input-wrap">
              <Phone size={15} className="auth-field__icon" />
              <input id="phone" type="tel" autoComplete="tel" inputMode="numeric" placeholder="5xx xxx xx xx" spellCheck={false} {...register("phone")} />
            </div>
            {errors.phone ? <span className="auth-field__error">{errors.phone.message}</span> : null}
          </div>

          <div className="auth-field">
            <label htmlFor="password">Şifre</label>
            <div className="auth-field__input-wrap">
              <LockKeyhole size={15} className="auth-field__icon" />
              <input id="password" type={showPassword ? "text" : "password"} autoComplete={mode === "login" ? "current-password" : "new-password"} placeholder={mode === "login" ? "Şifre" : "En az 8 karakter"} {...register("password")} />
              <button type="button" className="auth-field__toggle" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? "Şifreyi gizle" : "Şifreyi göster"}>
                {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
              </button>
            </div>
            {errors.password ? <span className="auth-field__error">{errors.password.message}</span> : null}
          </div>

          {formError ? <div className="auth-alert auth-alert--danger">{formError}</div> : null}

          <button type="submit" className="auth-submit" data-success={successState} disabled={isSubmitting || successState}>
            {successState ? (
              <><Check size={15} /> Yönlendiriliyor</>
            ) : isSubmitting ? (
              <><Loader2 size={15} className="animate-spin" /> İşleniyor</>
            ) : mode === "login" ? "Giriş Yap" : "Kayıt Ol"}
          </button>
        </fieldset>
      </form>

      <div className="auth-switch">
        {mode === "login" ? <Link href="/auth/forgot-password">Şifremi unuttum</Link> : null}
        <Link href={mode === "login" ? "/auth/register" : "/auth/login"}>
          {mode === "login" ? "Kayıt ol" : "Giriş yap"}
        </Link>
        <Link href="/" className="auth-switch__home">Ana sayfa</Link>
      </div>
    </div>
  );
}
