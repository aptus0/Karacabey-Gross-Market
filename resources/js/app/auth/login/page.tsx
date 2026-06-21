import type { Metadata } from "next";
import { Suspense } from "react";
import { AuthExperience } from "@/app/_components/AuthExperience";
import { AuthLayout } from "@/app/_layouts/AuthLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Giriş",
  description: "Karacabey Gross Market müşteri girişi ve hesap erişimi.",
  path: "/auth/login",
  keywords: ["giriş", "müşteri hesabı", "oturum aç"],
  robots: {
    index: false,
    follow: false,
  },
});

export default function LoginPage() {
  return (
    <AuthLayout>
      <Suspense fallback={null}>
        <AuthExperience mode="login" />
      </Suspense>
    </AuthLayout>
  );
}
