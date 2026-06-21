import Link from "next/link";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { AuthLayout } from "@/app/_layouts/AuthLayout";

export default function AuthPage() {
  return (
    <AuthLayout>
      <section className="grid w-full gap-6">
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 rounded-[32px] border border-[#E4E7EB] bg-[linear-gradient(160deg,#FFFFFF_0%,#FFF6EC_100%)] p-8 shadow-[0_24px_64px_rgba(43,47,54,0.08)] sm:p-10">
          <div className="inline-flex w-fit rounded-2xl border border-[#FFE1C2] bg-white p-3 shadow-sm">
            <KgmLogo />
          </div>
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div className="space-y-3">
              <p className="text-xs font-black uppercase tracking-[0.16em] text-[#FF7A00]">
                Karacabey Gross hesabı
              </p>
              <h1 className="text-4xl font-black leading-tight text-[#2B2F36] sm:text-5xl">
                Hızlı giriş, güvenli checkout ve daha akıcı müşteri deneyimi.
              </h1>
              <p className="max-w-3xl text-base leading-8 text-[#5F6670]">
                Siparişlerinizi, kayıtlı adreslerinizi ve gelecekte aktif olacak sosyal giriş akışlarını tek hesap çatısında topluyoruz.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link
                className="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#FF7A00] px-5 text-sm font-black text-white shadow-[0_18px_36px_rgba(255,122,0,0.22)]"
                href="/auth/login"
              >
                Giriş Yap
              </Link>
              <Link
                className="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#E4E7EB] bg-white px-5 text-sm font-black text-[#2B2F36]"
                href="/auth/register"
              >
                Kayıt Ol
              </Link>
            </div>
          </div>
        </div>
      </section>
    </AuthLayout>
  );
}
