"use client";

import Link from "next/link";
import { AlertTriangle, CheckCircle2 } from "lucide-react";
import { useSearchParams } from "next/navigation";

type CheckoutResultPanelProps = {
  result: "success" | "fail";
};

export function CheckoutResultPanel({ result }: CheckoutResultPanelProps) {
  const searchParams = useSearchParams();
  const merchantOid = searchParams.get("merchant_oid") ?? searchParams.get("oid");
  const reasonCode = searchParams.get("failed_reason_code") ?? searchParams.get("code");
  const reasonMessage = searchParams.get("failed_reason_msg") ?? searchParams.get("message");
  const isSuccess = result === "success";

  return (
    <section className="result-panel">
      <div className="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF0E0] text-[#FF7A00]">
        {isSuccess ? <CheckCircle2 size={26} /> : <AlertTriangle size={26} />}
      </div>

      <p className="eyebrow">{isSuccess ? "Ödeme yönlendirmesi tamamlandı" : "Ödeme tamamlanmadı"}</p>
      <h1>{isSuccess ? "Siparişiniz kontrol ediliyor." : "İşlemi tekrar deneyebilirsiniz."}</h1>
      <p>
        {isSuccess
          ? "PayTR ödeme sonucunu sunucu callback bildirimiyle doğrular. Banka onayı geldikten sonra sipariş durumu otomatik güncellenir."
          : "Kart doğrulaması, 3D Secure veya banka cevabı nedeniyle işlem tamamlanmadıysa checkout ekranından tekrar deneyebilirsiniz."}
      </p>

      {(merchantOid || reasonCode || reasonMessage) && (
        <div className="mt-5 grid gap-2 rounded-2xl border border-[#EEF1F4] bg-[#FAFBFC] p-4 text-left text-sm text-[#5F6670]">
          {merchantOid ? (
            <div className="flex items-center justify-between gap-3">
              <span className="font-bold">Sipariş referansı</span>
              <code className="rounded-lg bg-white px-2 py-1 text-xs font-black text-[#2B2F36]">{merchantOid}</code>
            </div>
          ) : null}
          {reasonCode ? (
            <div className="flex items-center justify-between gap-3">
              <span className="font-bold">Hata kodu</span>
              <code className="rounded-lg bg-white px-2 py-1 text-xs font-black text-[#A32A18]">{reasonCode}</code>
            </div>
          ) : null}
          {reasonMessage ? <p className="font-semibold text-[#A32A18]">{reasonMessage}</p> : null}
        </div>
      )}

      <div className="split-actions">
        <Link className="primary-action" href={isSuccess ? "/account/orders" : "/checkout"}>
          {isSuccess ? "Siparişlerim" : "Checkout'a Dön"}
        </Link>
        <Link className="secondary-action" href="/products">
          Alışverişe Devam
        </Link>
      </div>
    </section>
  );
}
