import { siteUrl } from "@/lib/seo";

export const dynamic = "force-static";

export function GET() {
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<browserconfig>
  <msapplication>
    <tile>
      <square70x70logo src="${siteUrl}/assets/kgm-favicon-256.png" />
      <square150x150logo src="${siteUrl}/assets/kgm-favicon-256.png" />
      <wide310x150logo src="${siteUrl}/assets/kgm-logo.png" />
      <square310x310logo src="${siteUrl}/assets/kgm-logo-4k.png" />
      <TileColor>#111827</TileColor>
    </tile>
  </msapplication>
</browserconfig>`;

  return new Response(xml, {
    headers: {
      "Cache-Control": "public, max-age=86400, s-maxage=86400",
      "Content-Type": "application/xml; charset=utf-8",
    },
  });
}
