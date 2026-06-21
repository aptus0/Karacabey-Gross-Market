"use client";

import { Check, Loader2, LockKeyhole, Mail, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, useState } from "react";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { apiRequest, extractErrorMessage } from "@/lib/api";

type ResetMode = "forgot" | "reset";
type ResetResponse = { status: string; message: string };

export function PasswordResetExperience({ mode }: { mode: ResetMode }) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState(searchParams.get("email") ?? "");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setMessage(null);
    setSubmitting(true);

    try {
      if (mode === "reset" && password !== confirmation) {
        throw new Error("Yeni şifreler eşleşmiyor.");
      }

      const payload = await apiRequest<ResetResponse>(
        mode === "forgot" ? "/api/v1/auth/forgot-password" : "/api/v1/auth/reset-password",
        {
          method: "POST",
          timeoutMs: 12_000,
          body: JSON.stringify(
            mode === "forgot"
              ? { email: email.trim() }
              : {
                  email: email.trim(),
                  token: searchParams.get("token") ?? "",
                  password,
                  password_confirmation: confirmation,
                },
          ),
        },
      );
      setMessage(payload.message);
      if (mode === "reset") {
        window.setTimeout(() => router.replace("/auth/login"), 1800);
      }
    } catch (caughtError) {
      setError(extractErrorMessage(caughtError, "İşlem tamamlanamadı."));
    } finally {
      setSubmitting(false);
    }
  }

  const resetLinkMissing = mode === "reset" && (!searchParams.get("token") || !email);

  return (
    <div className="auth-card auth-card--minimal" aria-busy={submitting}>
      <Link href="/" className="auth-card__logo" aria-label="Ana sayfa">
        <KgmLogo variant="header" />
      </Link>

      <div className="auth-card__header">
        <span className="auth-card__badge"><ShieldCheck size={13} /> Güvenli hesap</span>
        <h1 className="auth-card__title">{mode === "forgot" ? "Şifrenizi yenileyin" : "Yeni şifrenizi belirleyin"}</h1>
        <p className="auth-card__sub">
          {mode === "forgot"
            ? "Hesabınıza bağlı e-posta adresine tek kullanımlık bir bağlantı göndereceğiz."
            : "Bağlantı tek kullanımlıktır. İşlem tamamlandığında mevcut oturumlarınız kapatılır."}
        </p>
      </div>

      <form className="auth-form" onSubmit={submit} noValidate>
        <fieldset className="auth-form__fieldset" disabled={submitting || Boolean(message)}>
          <div className="auth-field">
            <label htmlFor="reset-email">E-posta</label>
            <div className="auth-field__input-wrap">
              <Mail size={15} className="auth-field__icon" />
              <input
                id="reset-email"
                type="email"
                autoComplete="email"
                placeholder="ornek@eposta.com"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
                readOnly={mode === "reset"}
              />
            </div>
          </div>

          {mode === "reset" ? (
            <>
              <div className="auth-field">
                <label htmlFor="reset-password">Yeni şifre</label>
                <div className="auth-field__input-wrap">
                  <LockKeyhole size={15} className="auth-field__icon" />
                  <input id="reset-password" type="password" autoComplete="new-password" minLength={8} value={password} onChange={(event) => setPassword(event.target.value)} required />
                </div>
              </div>
              <div className="auth-field">
                <label htmlFor="reset-confirmation">Yeni şifre tekrar</label>
                <div className="auth-field__input-wrap">
                  <LockKeyhole size={15} className="auth-field__icon" />
                  <input id="reset-confirmation" type="password" autoComplete="new-password" minLength={8} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required />
                </div>
              </div>
            </>
          ) : null}

          {resetLinkMissing ? <div className="auth-alert auth-alert--danger">Şifre sıfırlama bağlantısı eksik veya geçersiz.</div> : null}
          {error ? <div className="auth-alert auth-alert--danger">{error}</div> : null}
          {message ? <div className="auth-alert"><Check size={16} /> {message}</div> : null}

          <button type="submit" className="auth-submit" data-success={Boolean(message)} disabled={submitting || Boolean(message) || resetLinkMissing}>
            {submitting ? <><Loader2 size={15} className="animate-spin" /> İşleniyor</> : mode === "forgot" ? "Bağlantı gönder" : "Şifreyi güncelle"}
          </button>
        </fieldset>
      </form>

      <div className="auth-switch">
        <Link href="/auth/login">Giriş ekranına dön</Link>
        <Link href="/" className="auth-switch__home">Ana sayfa</Link>
      </div>
    </div>
  );
}
