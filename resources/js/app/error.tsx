"use client";

import Link from "next/link";
import { AlertCircle, Home, RefreshCw } from "lucide-react";

type ErrorPageProps = {
  error: Error & {
    digest?: string;
  };
  reset: () => void;
};

export default function ErrorPage({ error, reset }: ErrorPageProps) {
  return (
    <main className="kgm-error-page">
      <section className="kgm-error-surface">
        <p className="eyebrow">Sistem Bildirimi</p>
        <div className="kgm-error-icon kgm-error-icon--danger">
          <AlertCircle size={28} />
        </div>
        <h1>Bir şeyler ters gitti</h1>
        <p>
          Sayfa yüklenirken beklenmeyen bir sorun oluştu. Tekrar deneyebilir veya güvenli şekilde
          ana sayfaya dönebilirsiniz.
        </p>
        {error.digest ? (
          <p className="text-sm text-[#6B7177]">Hata kodu: {error.digest}</p>
        ) : null}
        <div className="kgm-error-actions">
          <button type="button" className="primary-action" onClick={() => reset()}>
            <RefreshCw size={16} />
            Tekrar Dene
          </button>
          <Link href="/" className="secondary-action">
            <Home size={16} />
            Ana Sayfa
          </Link>
        </div>
      </section>
    </main>
  );
}
