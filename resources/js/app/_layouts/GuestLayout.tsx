import type { ReactNode } from "react";
import { Suspense } from "react";
import { BottomNavigation } from "@/app/_components/BottomNavigation";
import { Footer } from "@/app/_components/Footer";
import { Header } from "@/app/_components/Header";
import { MobileHeader } from "@/app/_components/MobileHeader";
import { SupportWidget } from "@/app/_components/SupportWidget";

type GuestLayoutProps = {
  children: ReactNode;
};

export function GuestLayout({ children }: GuestLayoutProps) {
  return (
    <>
      <Header hideOnMobile />
      <Suspense fallback={null}>
        <MobileHeader />
      </Suspense>
      {children}
      <SupportWidget />
      <Footer />
      <BottomNavigation />
    </>
  );
}
