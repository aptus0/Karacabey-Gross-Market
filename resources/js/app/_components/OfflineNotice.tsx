"use client";

import { WifiOff } from "lucide-react";
import { useEffect, useState } from "react";

export function OfflineNotice() {
  const [isOffline, setIsOffline] = useState(false);

  useEffect(() => {
    const sync = () => setIsOffline(typeof navigator !== "undefined" && navigator.onLine === false);

    sync();
    window.addEventListener("online", sync);
    window.addEventListener("offline", sync);

    return () => {
      window.removeEventListener("online", sync);
      window.removeEventListener("offline", sync);
    };
  }, []);

  if (!isOffline) {
    return null;
  }

  return (
    <div className="offline-notice" role="status" aria-live="polite">
      <WifiOff size={16} />
      <span>Baglanti yok. Urun listesi onbellekten gosteriliyor; giris, kayit ve sepet islemleri kisitli olabilir.</span>
    </div>
  );
}
