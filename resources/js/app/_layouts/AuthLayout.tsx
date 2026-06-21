import type { ReactNode } from "react";
import Link from "next/link";
import { Footer } from "@/app/_components/Footer";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { ArrowLeft, ShieldCheck, ShoppingBag } from "lucide-react";

type AuthLayoutProps = {
  children: ReactNode;
};

export function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <>
      <header className="auth-header">
        <div className="auth-header__inner">
          <Link href="/" className="auth-header__brand" aria-label="Karacabey Gross Market ana sayfa">
            <KgmLogo variant="header" />
          </Link>

          <div className="auth-header__meta" aria-label="Güvenli hesap işlemleri">
            <span><ShieldCheck size={15} /> Güvenli oturum</span>
            <span><ShoppingBag size={15} /> Hızlı checkout</span>
          </div>

          <Link href="/" className="auth-header__back">
            <ArrowLeft size={16} />
            Mağazaya dön
          </Link>
        </div>
      </header>
      <main className="auth-shell">{children}</main>
      <Footer compact />
    </>
  );
}
