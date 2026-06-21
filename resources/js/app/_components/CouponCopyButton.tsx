"use client";

import { useState } from "react";
import { Check, Copy } from "lucide-react";

type CouponCopyButtonProps = {
  code: string;
};

export function CouponCopyButton({ code }: CouponCopyButtonProps) {
  const [copied, setCopied] = useState(false);

  async function handleClick() {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1600);
    } catch {
      setCopied(false);
    }
  }

  return (
    <button
      type="button"
      className="coupon-copy-btn inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#FF7A00] text-white transition hover:bg-[#E06500]"
      aria-label={copied ? "Kod kopyalandı" : "Kodu kopyala"}
      title={copied ? "Kod kopyalandı" : "Kodu kopyala"}
      onClick={handleClick}
    >
      {copied ? <Check size={14} /> : <Copy size={14} />}
    </button>
  );
}
