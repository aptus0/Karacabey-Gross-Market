"use client";

import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";

export type DeliveryLocation = {
  lat: number;
  lng: number;
  label: string;
  address?: string;
  city?: string;
  district?: string;
  source: "browser" | "manual" | "checkout";
  updatedAt: string;
};

type GoogleGeocodeAddressComponent = {
  long_name: string;
  short_name: string;
  types: string[];
};

type GoogleGeocodeResult = {
  formatted_address?: string;
  address_components?: GoogleGeocodeAddressComponent[];
};

type GoogleGeocodeResponse = {
  status?: string;
  results?: GoogleGeocodeResult[];
};

function findAddressPart(components: GoogleGeocodeAddressComponent[] = [], type: string) {
  return components.find((component) => component.types.includes(type))?.long_name;
}

export async function reverseGeocodeDeliveryLocation(
  lat: number,
  lng: number,
): Promise<Pick<DeliveryLocation, "address" | "city" | "district" | "label">> {
  const apiKey = process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY;

  if (!apiKey) {
    return { label: `${lat}, ${lng}` };
  }

  const response = await fetch(
    `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&language=tr&key=${apiKey}`,
  );

  if (!response.ok) {
    return { label: `${lat}, ${lng}` };
  }

  const payload = (await response.json()) as GoogleGeocodeResponse;
  const result = payload.status === "OK" ? payload.results?.[0] : null;
  const components = result?.address_components ?? [];
  const city = findAddressPart(components, "administrative_area_level_1");
  const district =
    findAddressPart(components, "administrative_area_level_2") ??
    findAddressPart(components, "locality");
  const address = result?.formatted_address;

  return {
    address,
    city,
    district,
    label: district && city ? `${district}, ${city}` : address ?? `${lat}, ${lng}`,
  };
}

type LocationState = {
  location: DeliveryLocation | null;
  isHydrated: boolean;
  setLocation: (location: Omit<DeliveryLocation, "updatedAt"> & { updatedAt?: string }) => void;
  clearLocation: () => void;
  markHydrated: () => void;
};

export const useLocationStore = create<LocationState>()(
  persist(
    (set) => ({
      location: null,
      isHydrated: false,
      setLocation: (location) =>
        set({
          location: {
            ...location,
            updatedAt: location.updatedAt ?? new Date().toISOString(),
          },
          isHydrated: true,
        }),
      clearLocation: () => set({ location: null, isHydrated: true }),
      markHydrated: () => set({ isHydrated: true }),
    }),
    {
      name: "kgm-delivery-location",
      version: 1,
      migrate: (persistedState) => persistedState,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ location: state.location }),
      onRehydrateStorage: () => (state) => {
        state?.markHydrated();
      },
    },
  ),
);
