const siteUrl = (process.env.NEXT_PUBLIC_SITE_URL || "https://karacabeygrossmarket.com").replace(/\/+$/, "");
const indexNowKey = process.env.INDEXNOW_KEY;
const endpoint = process.env.INDEXNOW_ENDPOINT || "https://api.indexnow.org/indexnow";
const maxUrls = Number.parseInt(process.env.INDEXNOW_MAX_URLS || "10000", 10);

if (!indexNowKey) {
  console.error("INDEXNOW_KEY is required.");
  process.exit(1);
}

function extractUrls(xml) {
  return Array.from(xml.matchAll(/<loc>(.*?)<\/loc>/g))
    .map((match) => match[1])
    .filter((url) => url.startsWith(siteUrl))
    .slice(0, maxUrls);
}

const sitemapResponse = await fetch(`${siteUrl}/sitemap.xml`, {
  headers: { Accept: "application/xml,text/xml" },
});

if (!sitemapResponse.ok) {
  console.error(`Sitemap request failed with ${sitemapResponse.status}.`);
  process.exit(1);
}

const sitemapXml = await sitemapResponse.text();
const urlList = extractUrls(sitemapXml);

if (urlList.length === 0) {
  console.error("No URLs found in sitemap.xml.");
  process.exit(1);
}

const payload = {
  host: new URL(siteUrl).host,
  key: indexNowKey,
  keyLocation: `${siteUrl}/indexnow/${indexNowKey}.txt`,
  urlList,
};

const response = await fetch(endpoint, {
  method: "POST",
  headers: {
    "Content-Type": "application/json; charset=utf-8",
  },
  body: JSON.stringify(payload),
});

if (!response.ok && response.status !== 202) {
  const body = await response.text();
  console.error(`IndexNow submit failed with ${response.status}: ${body}`);
  process.exit(1);
}

console.log(`IndexNow submitted ${urlList.length} URLs with status ${response.status}.`);
