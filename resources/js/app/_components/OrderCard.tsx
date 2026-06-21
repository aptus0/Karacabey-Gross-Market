import Link from "next/link";
import { Check, Clock3, Package, Truck } from "lucide-react";
import { formatCartMoney, formatOrderDate, orderProgress, orderStatusColor } from "@/lib/account";
import type { UserOrder } from "@/lib/account";
import { cn } from "@/lib/utils";

type OrderCardProps = { order: UserOrder };

function statusIcon(status: string) {
  if (["completed", "delivered"].includes(status)) return <Check size={16} />;
  if (["shipping", "in_delivery"].includes(status)) return <Truck size={16} />;
  if (["awaiting_payment", "reviewing", "paid", "preparing"].includes(status)) return <Clock3 size={16} />;
  return <Package size={16} />;
}

export function OrderCard({ order }: OrderCardProps) {
  const items = Array.isArray(order.items) ? order.items : [];
  const progress = orderProgress(order.status);

  return (
    <article className="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
      <div className="border-b border-slate-200 bg-slate-50 p-6 sm:flex sm:items-center sm:justify-between">
        <dl className="grid grid-cols-2 gap-6 sm:grid-cols-4 sm:gap-8 flex-1">
          <div>
            <dt className="text-xs font-semibold tracking-wider text-slate-500 uppercase">Sipariş No</dt>
            <dd className="mt-1.5 text-sm font-bold text-slate-900">{order.merchant_oid}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold tracking-wider text-slate-500 uppercase">Tarih</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-700">{formatOrderDate(order.created_at)}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold tracking-wider text-slate-500 uppercase">Tutar</dt>
            <dd className="mt-1.5 text-sm font-bold text-slate-900">{formatCartMoney(order.total_cents)}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold tracking-wider text-slate-500 uppercase">Durum</dt>
            <dd className={cn("mt-1.5 flex items-center gap-1.5 text-sm font-bold", orderStatusColor(order.status))}>
              {statusIcon(order.status)}
              {order.status_label}
            </dd>
          </div>
        </dl>
      </div>

      <div className="p-6">
        <div className="flow-root">
          <ul className="-my-6 divide-y divide-slate-100">
            {items.map((item) => (
              <li key={item.id} className="flex items-center gap-4 py-6">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
                  <Package size={24} strokeWidth={1.5} />
                </div>
                <div className="min-w-0 flex-1">
                  <h4 className="text-sm font-semibold text-slate-900 line-clamp-1">{item.name}</h4>
                  <p className="mt-1 text-sm text-slate-500">{item.quantity} Adet</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-bold text-slate-900">{formatCartMoney(item.unit_price_cents)}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>
        
        <div className="mt-6 flex items-center justify-between border-t border-slate-100 pt-6">
          <div className="flex items-center gap-3">
            <span className="text-sm font-medium text-slate-500 hidden sm:inline-block">İlerleme:</span>
            <div className="h-2 w-24 sm:w-32 overflow-hidden rounded-full bg-slate-100">
              <div className="h-full rounded-full bg-orange-500 transition-all duration-500" style={{ width: `${progress}%` }} />
            </div>
          </div>
          <Link href="/bize-ulasin" className="text-sm font-semibold text-orange-600 transition-colors hover:text-orange-700">
            Destek Al &rarr;
          </Link>
        </div>
      </div>
    </article>
  );
}
