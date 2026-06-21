import type { Metadata } from "next";
import { Suspense } from "react";
import { PasswordResetExperience } from "@/app/_components/PasswordResetExperience";
import { AuthLayout } from "@/app/_layouts/AuthLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Yeni Şifre",
  description: "Karacabey Gross Market hesabınız için yeni şifre belirleyin.",
  path: "/auth/reset-password",
  robots: { index: false, follow: false },
});

export default function ResetPasswordPage() {
  return (
    <AuthLayout>
      <Suspense fallback={null}>
        <PasswordResetExperience mode="reset" />
      </Suspense>
    </AuthLayout>
  );
}
