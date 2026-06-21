import type { Metadata } from "next";
import { Suspense } from "react";
import { PasswordResetExperience } from "@/app/_components/PasswordResetExperience";
import { AuthLayout } from "@/app/_layouts/AuthLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Şifremi Unuttum",
  description: "Karacabey Gross Market hesabınız için güvenli şifre sıfırlama bağlantısı isteyin.",
  path: "/auth/forgot-password",
  robots: { index: false, follow: false },
});

export default function ForgotPasswordPage() {
  return (
    <AuthLayout>
      <Suspense fallback={null}>
        <PasswordResetExperience mode="forgot" />
      </Suspense>
    </AuthLayout>
  );
}
