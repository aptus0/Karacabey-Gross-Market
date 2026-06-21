"use client";

import { useEffect, useRef, useState } from "react";
import { CheckCircle2, ChevronDown, Loader2, Tag, X } from "lucide-react";
import { Button } from "@/app/_components/ui/button";
import { Input } from "@/app/_components/ui/input";
import { formatCartMoney, type AppliedCoupon } from "@/lib/cart";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";

export type CouponData = AppliedCoupon;

type CouponInputProps = {
  appliedCoupon: CouponData | null;
  onApply: (code: string) => Promise<unknown> | void;
  onRemove: () => Promise<unknown> | void;
  disabled?: boolean;
};

export function CouponInput({
  appliedCoupon,
  onApply,
  onRemove,
  disabled = false,
}: CouponInputProps) {
  const [open, setOpen] = useState(Boolean(appliedCoupon));
  const [code, setCode] = useState(appliedCoupon?.code ?? "");
  const [validating, setValidating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    let active = true;
    queueMicrotask(() => {
      if (!active) return;
      setCode(appliedCoupon?.code ?? "");
      setOpen(Boolean(appliedCoupon));
      setError(null);
    });

    return () => {
      active = false;
    };
  }, [appliedCoupon]);

  function handleToggle() {
    setOpen((prev) => {
      const next = !prev;
      if (next) setTimeout(() => inputRef.current?.focus(), 50);
      return next;
    });
    setError(null);
  }

  function handleRemove() {
    setCode("");
    setError(null);
    setOpen(false);
    onRemove();
  }

  async function handleApply() {
    const trimmed = code.trim().toUpperCase();
    if (!trimmed) return;

    setValidating(true);
    setError(null);

    try {
      await onApply(trimmed);
      track("coupon_apply", { coupon_code: trimmed });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Kupon uygulanamadı.");
    } finally {
      setValidating(false);
    }
  }

  if (appliedCoupon) {
    return (
      <div className="kgm-coupon-applied">
        <div className="kgm-coupon-applied__body">
          <CheckCircle2 size={16} className="shrink-0 text-[#16A34A]" />
          <div className="min-w-0">
            <p className="kgm-coupon-applied__code">
              {appliedCoupon.code}
            </p>
            <p className="kgm-coupon-applied__meta">
              {appliedCoupon.discount_type === "percent"
                ? `%${appliedCoupon.discount_value} indirim`
                : formatCartMoney(appliedCoupon.discount_cents)}{" "}
              uygulandı
            </p>
          </div>
        </div>
        <button
          type="button"
          onClick={() => {
            track("coupon_remove", { coupon_code: appliedCoupon.code });
            handleRemove();
          }}
          className="kgm-coupon-applied__remove"
          aria-label="Kuponu kaldır"
        >
          <X size={15} />
        </button>
      </div>
    );
  }

  return (
    <div className="kgm-coupon">
      <button
        type="button"
        onClick={handleToggle}
        className="kgm-coupon__toggle"
      >
        <span className="kgm-coupon__toggle-label">
          <Tag size={14} className="text-[#FF7A00]" />
          Kuponum var
        </span>
        <ChevronDown
          size={15}
          className={cn("transition-transform duration-200", open && "rotate-180")}
        />
      </button>

      <div
        className={cn(
          "grid overflow-hidden transition-all duration-200",
          open ? "grid-rows-[1fr] opacity-100 pt-2" : "grid-rows-[0fr] opacity-0",
        )}
      >
        <div className="overflow-hidden">
          <div className="kgm-coupon__body">
            <div className="kgm-coupon__row">
              <Input
                ref={inputRef}
                value={code}
                onChange={(e) => {
                  setCode(e.target.value.toUpperCase());
                  if (error) setError(null);
                }}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    handleApply();
                  }
                }}
                placeholder="KUPON KODU"
                maxLength={64}
                disabled={disabled || validating}
                className={cn(
                  "kgm-coupon__input flex-1 font-mono tracking-widest placeholder:font-sans placeholder:not-italic placeholder:tracking-normal",
                  error && "border-[#EF4444] focus-visible:ring-[#EF4444]",
                )}
                autoComplete="off"
                spellCheck={false}
              />
              <Button
                type="button"
                onClick={handleApply}
                disabled={!code.trim() || disabled || validating}
                className="kgm-coupon__button"
              >
                {validating ? (
                  <Loader2 size={15} className="animate-spin" />
                ) : (
                  "Uygula"
                )}
              </Button>
            </div>
            {error ? (
              <p className="text-xs font-semibold text-[#EF4444]">{error}</p>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}
