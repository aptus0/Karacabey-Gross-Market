import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";

const root = process.cwd();
const failures = [];

function fail(message) {
  failures.push(message);
}

function read(path) {
  return readFileSync(join(root, path), "utf8");
}

function assertFile(path) {
  if (!existsSync(join(root, path))) {
    fail(`Missing required SEO file: ${path}`);
  }
}

function walk(dir, files = []) {
  const absoluteDir = join(root, dir);

  for (const entry of readdirSync(absoluteDir)) {
    if (entry === "node_modules" || entry === ".next") {
      continue;
    }

    const absolutePath = join(absoluteDir, entry);
    const relativePath = relative(root, absolutePath);

    if (statSync(absolutePath).isDirectory()) {
      walk(relativePath, files);
    } else {
      files.push(relativePath);
    }
  }

  return files;
}

[
  "app/manifest.ts",
  "app/browserconfig.xml/route.ts",
  "app/google-merchant.xml/route.ts",
  "app/indexnow/[key]/route.ts",
  "app/opensearch.xml/route.ts",
  "app/robots.ts",
  "app/sitemap.ts",
  "lib/seo.ts",
  "public/seo/og-default.png",
  "public/seo/twitter-card.png",
].forEach(assertFile);

const seoSource = read("lib/seo.ts");
[
  "buildMetadata",
  "organizationSchema",
  "groceryStoreSchema",
  "websiteSchema",
  "breadcrumbSchema",
  "productSchema",
  "serviceSchema",
  "webApplicationSchema",
  "jsonLdGraph",
].forEach((exportName) => {
  if (!seoSource.includes(`function ${exportName}`)) {
    fail(`lib/seo.ts must export ${exportName}.`);
  }
});

const appAndLibFiles = walk("app").concat(walk("lib"));
const legacyCategoryLinks = appAndLibFiles
  .filter((path) => /\.(tsx?|jsx?)$/.test(path))
  .filter((path) => read(path).includes("/products?category="));

if (legacyCategoryLinks.length > 0) {
  fail(`Legacy category query links found: ${legacyCategoryLinks.join(", ")}`);
}

const robotsSource = read("app/robots.ts");
[
  "/google-merchant.xml",
  "/indexnow/",
  "/opensearch.xml",
  "/account",
  "/auth",
  "/checkout",
  "/favorites",
  "/hesabim",
  "/*?q=",
  "/*?page=",
  "/*?category=",
].forEach((rule) => {
  if (!robotsSource.includes(rule)) {
    fail(`robots.ts is missing rule: ${rule}`);
  }
});

const sitemapSource = read("app/sitemap.ts");
[
  "fetchStorefrontProducts",
  "fetchStorefrontCategories",
  "fetchAllSitemapProducts",
  "fetchCampaignSitemapItems",
  "alternates",
  "images",
  "uniqueEntries",
  "/kategori/",
  "/product/",
  "/kampanyalar/",
  "/blog/",
].forEach((signal) => {
  if (!sitemapSource.includes(signal)) {
    fail(`sitemap.ts is missing signal: ${signal}`);
  }
});

const merchantFeedSource = read("app/google-merchant.xml/route.ts");
[
  "xmlns:g=\"http://base.google.com/ns/1.0\"",
  "fetchStorefrontProducts",
  "<g:id>",
  "<g:title>",
  "<g:description>",
  "<g:link>",
  "<g:image_link>",
  "<g:availability>",
  "<g:price>",
  "<g:condition>new</g:condition>",
  "<g:google_product_category>",
  "application/xml",
].forEach((signal) => {
  if (!merchantFeedSource.includes(signal)) {
    fail(`google-merchant.xml route is missing Merchant signal: ${signal}`);
  }
});

const layoutSource = read("app/layout.tsx");
[
  "metadataBase",
  "manifest",
  "viewport",
  "siteJsonLd",
  "YANDEX_SITE_VERIFICATION",
  "BING_SITE_VERIFICATION",
  "msvalidate.01",
].forEach((signal) => {
  if (!layoutSource.includes(signal)) {
    fail(`layout.tsx is missing global SEO signal: ${signal}`);
  }
});

const headSource = read("app/head.tsx");
[
  "application/opensearchdescription+xml",
  "/opensearch.xml",
].forEach((signal) => {
  if (!headSource.includes(signal)) {
    fail(`head.tsx is missing browser discovery signal: ${signal}`);
  }
});

const openSearchSource = read("app/opensearch.xml/route.ts");
[
  "OpenSearchDescription",
  "SearchForm",
  "{searchTerms}",
  "application/opensearchdescription+xml",
].forEach((signal) => {
  if (!openSearchSource.includes(signal)) {
    fail(`opensearch.xml route is missing signal: ${signal}`);
  }
});

const indexNowSource = read("app/indexnow/[key]/route.ts");
[
  "INDEXNOW_KEY",
  ".txt",
  "text/plain",
].forEach((signal) => {
  if (!indexNowSource.includes(signal)) {
    fail(`IndexNow key route is missing signal: ${signal}`);
  }
});

const indexNowSubmitSource = read("scripts/submit-indexnow.mjs");
[
  "INDEXNOW_KEY",
  "api.indexnow.org",
  "keyLocation",
  "sitemap.xml",
].forEach((signal) => {
  if (!indexNowSubmitSource.includes(signal)) {
    fail(`IndexNow submit script is missing signal: ${signal}`);
  }
});

const browserConfigSource = read("app/browserconfig.xml/route.ts");
[
  "browserconfig",
  "msapplication",
  "TileColor",
].forEach((signal) => {
  if (!browserConfigSource.includes(signal)) {
    fail(`browserconfig.xml route is missing signal: ${signal}`);
  }
});

if (failures.length > 0) {
  console.error("SEO audit failed:");
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log("SEO audit passed.");
