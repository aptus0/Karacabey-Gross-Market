#!/usr/bin/env node
import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";
import { Script } from "node:vm";

const buildDir = join(process.cwd(), ".next");
if (!existsSync(buildDir)) {
  console.error(".next bulunamadı. Önce secure build çalıştır.");
  process.exit(1);
}
const files = walk(buildDir);
const maps = files.filter((file) => file.endsWith(".map"));
if (maps.length) {
  console.error(`Source map dosyaları bulundu: ${maps.slice(0, 5).join(", ")}`);
  process.exit(1);
}
const protectedClassPrefix = "(?:kgm-|site-header|checkout-|form-alert|field-error|primary-action|product-gallery|footer-brand|footer-bank|mobile-header|bottom-nav|search-bar|product-info|catalog|auth|price-box|shipping-free-label|mobile-summary-toggle)";
const cssClassLeakRe = new RegExp(`\\.${protectedClassPrefix}[A-Za-z0-9_-]*`);
const markupClassLeakRe = new RegExp(`class(?:Name)?=["'][^"']*${protectedClassPrefix}`);
const jsClassLeakRe = new RegExp(`className["']?\\s*[:=]\\s*["'][^"']*${protectedClassPrefix}`);
const publicClassLeak = files
  .filter((file) => /\.(?:js|css|html|rsc)$/i.test(file))
  .some((file) => {
    const content = readFileSync(file, "utf8");
    if (/\.css$/i.test(file)) return cssClassLeakRe.test(content);
    return markupClassLeakRe.test(content) || jsClassLeakRe.test(content);
  });
if (publicClassLeak) {
  console.error("Build çıktısında kgm-* özel class adı kaldı. harden-next-build scriptini çalıştır.");
  process.exit(1);
}

for (const file of files.filter((file) => /\.js$/i.test(file) && file.includes(`${join(".next", "static")}${"/"}`))) {
  try {
    new Script(readFileSync(file, "utf8"), { filename: file });
  } catch (error) {
    console.error(`Client JS syntax hatası: ${file}`);
    console.error(error.message);
    process.exit(1);
  }
}

console.log("Security build check temiz: source map yok, kgm-* özel class leak yok, client JS syntax sağlam.");

function walk(dir) {
  const out = [];
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);
    const stat = statSync(path);
    if (stat.isDirectory()) out.push(...walk(path));
    else out.push(path);
  }
  return out;
}
