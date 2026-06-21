"use client";

import { ExternalLink, Loader2, MapPin, Navigation } from "lucide-react";
import { useState } from "react";
import {
  reverseGeocodeDeliveryLocation,
  useLocationStore,
  type DeliveryLocation,
} from "@/lib/location-store";

type AddressMapPickerProps = {
  onLocation?: (location: DeliveryLocation) => void;
  compact?: boolean;
  title?: string;
  description?: string;
};

export function AddressMapPicker({
  onLocation,
  compact = false,
  title = "Google Maps konum desteği",
  description = "Konumunu algılayıp teslimat adresine bağlayalım.",
}: AddressMapPickerProps) {
  const [status, setStatus] = useState<string>("Konum seçimi opsiyonel.");
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [isLocating, setIsLocating] = useState(false);
  const savedLocation = useLocationStore((state) => state.location);
  const setSavedLocation = useLocationStore((state) => state.setLocation);

  function pickCurrentLocation() {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setStatus("Tarayıcı konum iznini desteklemiyor.");
      return;
    }

    setStatus("Konum alınıyor...");
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const lat = Number(position.coords.latitude.toFixed(6));
        const lng = Number(position.coords.longitude.toFixed(6));
        const resolved = await reverseGeocodeDeliveryLocation(lat, lng).catch(() => ({
          address: undefined,
          city: undefined,
          district: undefined,
          label: `${lat}, ${lng}`,
        }));
        const location: DeliveryLocation = {
          lat,
          lng,
          label: resolved.label,
          address: resolved.address,
          city: resolved.city,
          district: resolved.district,
          source: "browser",
          updatedAt: new Date().toISOString(),
        };

        setCoords({ lat, lng });
        setSavedLocation(location);
        setStatus(resolved.address ? "Konum ve adres algılandı." : "Konum alındı. Adres notunu kontrol et.");
        setIsLocating(false);
        onLocation?.(location);
      },
      () => {
        setIsLocating(false);
        setStatus("Konum izni alınamadı. Adresi manuel girebilirsin.");
      },
      { enableHighAccuracy: true, timeout: 9000, maximumAge: 60_000 },
    );
  }

  const resolvedCoords = coords ?? savedLocation;
  const mapsHref = resolvedCoords
    ? `https://www.google.com/maps/search/?api=1&query=${resolvedCoords.lat},${resolvedCoords.lng}`
    : "https://www.google.com/maps";

  return (
    <div className={`kgm-map-picker${compact ? " kgm-map-picker--compact" : ""}`}>
      <div className="kgm-map-picker__icon"><MapPin size={17} /></div>
      <div className="min-w-0 flex-1">
        <strong>{title}</strong>
        <span>{savedLocation?.label ?? description}</span>
        <small>{status}</small>
      </div>
      <button type="button" onClick={pickCurrentLocation} className="kgm-map-picker__button" disabled={isLocating}>
        {isLocating ? <Loader2 size={14} className="animate-spin" /> : <Navigation size={14} />}
        {isLocating ? "Alınıyor" : "Konum Al"}
      </button>
      <a href={mapsHref} target="_blank" rel="noreferrer" className="kgm-map-picker__ghost" aria-label="Google Maps aç">
        <ExternalLink size={14} />
      </a>
    </div>
  );
}
