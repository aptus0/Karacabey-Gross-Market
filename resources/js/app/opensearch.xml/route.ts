import { siteName, siteUrl } from "@/lib/seo";

export const dynamic = "force-static";

function escapeXml(value: string) {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

export function GET() {
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName>KGM</ShortName>
  <Description>${escapeXml(`${siteName} ürün arama`)}</Description>
  <InputEncoding>UTF-8</InputEncoding>
  <Image width="16" height="16" type="image/x-icon">${escapeXml(`${siteUrl}/favicon.ico`)}</Image>
  <Url type="text/html" method="get" template="${escapeXml(`${siteUrl}/products?q={searchTerms}`)}" />
  <Url type="application/opensearchdescription+xml" rel="self" template="${escapeXml(`${siteUrl}/opensearch.xml`)}" />
  <SearchForm>${escapeXml(`${siteUrl}/products`)}</SearchForm>
</OpenSearchDescription>`;

  return new Response(xml, {
    headers: {
      "Cache-Control": "public, max-age=86400, s-maxage=86400",
      "Content-Type": "application/opensearchdescription+xml; charset=utf-8",
    },
  });
}
