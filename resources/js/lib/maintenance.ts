import "server-only";

export type MaintenanceStatus = {
  enabled: boolean;
  active: boolean;
  title: string;
  message: string;
  starts_at?: string | null;
  ends_at?: string | null;
  channels: {
    storefront: boolean;
    checkout: boolean;
    api_writes: boolean;
    mobile: boolean;
  };
  support?: {
    phone?: string;
    whatsapp?: string;
    email?: string;
  };
};

function stripTrailingSlash(value: string | null | undefined) {
  return value ? value.replace(/\/+$/, "") : "";
}

export async function fetchMaintenanceStatus(): Promise<MaintenanceStatus | null> {
  const base = stripTrailingSlash(
    process.env.NEXT_PUBLIC_MAINTENANCE_STATUS_URL
      ?? process.env.NEXT_PUBLIC_PANEL_URL
      ?? process.env.PANEL_URL
      ?? "https://panel.karacabeygrossmarket.com",
  );
  if (!base) return null;

  try {
    const response = await fetch(`${base}/api/v1/system/status`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!response.ok) return null;
    const payload = await response.json() as { data?: { maintenance?: MaintenanceStatus } };

    return payload.data?.maintenance ?? null;
  } catch {
    return null;
  }
}
