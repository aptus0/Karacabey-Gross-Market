"use client";

import type { ReactNode } from "react";

type CheckoutLayoutProps = {
  children: ReactNode;
};

export function CheckoutLayout({ children }: CheckoutLayoutProps) {
  return (
    <div className="checkout-layout-wrapper">
      <main className="checkout-secure-main">
        {children}
      </main>
    </div>
  );
}
