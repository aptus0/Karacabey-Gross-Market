"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export function MobileCatalogRedirect() {
  const router = useRouter();

  useEffect(() => {
    if (window.matchMedia("(max-width: 980px)").matches) {
      router.replace("/products");
    }
  }, [router]);

  return null;
}
