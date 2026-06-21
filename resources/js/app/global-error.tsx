"use client";

import Link from "next/link";

type GlobalErrorProps = {
  error: Error & {
    digest?: string;
  };
  reset: () => void;
};

export default function GlobalError({ error, reset }: GlobalErrorProps) {
  return (
    <html lang="tr">
      <body className="s0">
        <main className="kgm-error-page">
          <section className="kgm-error-surface">
            <p className="eyebrow">Sistem Bildirimi</p>
            <div className="kgm-error-icon kgm-error-icon--danger">!</div>
            <h1>Bir şeyler ters gitti</h1>
            <p>
              Sayfa yüklenirken beklenmeyen bir hata oluştu. Hemen tekrar
              deneyebilir veya ana sayfaya dönebilirsiniz.
            </p>
            {error.message ? (
              <p className="text-sm text-[#6B7177]">{error.message}</p>
            ) : null}
            <div className="kgm-error-actions">
              <button type="button" className="primary-action" onClick={() => reset()}>
                Tekrar Dene
              </button>
              <Link href="/" className="secondary-action">
                Ana Sayfa
              </Link>
            </div>
          </section>
        </main>
      </body>
    </html>
  );
}
