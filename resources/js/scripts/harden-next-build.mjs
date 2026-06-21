#!/usr/bin/env node
import { createHash, randomBytes } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync, unlinkSync } from "node:fs";
import { join } from "node:path";

const root = process.cwd();
const buildDir = join(root, ".next");
const manifestDir = join(root, ".kgm-build");
const sourceRoots = [join(root, "app"), join(root, "lib")];
const protectedPrefixes = ["kgm-", "site-header", "checkout-", "form-alert", "field-error", "primary-action"];
const salt = process.env.KGM_CLASS_OBFUSCATION_SALT || randomBytes(12).toString("hex");
const enabled = (process.env.KGM_CLASS_OBFUSCATION ?? "true").toLowerCase() !== "false";

if (!existsSync(buildDir)) {
  console.error(".next klasörü bulunamadı. Önce `next build` çalıştır.");
  process.exit(1);
}

removeSourceMaps(buildDir);

if (!enabled) {
  console.log("KGM class obfuscation kapalı. Source map temizliği tamamlandı.");
  process.exit(0);
}

const candidates = [...new Set([
  ...collectClassCandidates(sourceRoots),
  ...collectBuildClassCandidates(buildDir),
])].sort((a, b) => b.length - a.length || a.localeCompare(b));
const mapping = new Map();
for (const name of candidates) {
  mapping.set(name, obfuscatedName(name));
}

const files = walk(buildDir).filter((file) => /\.(?:js|css|html|json|rsc)$/i.test(file));
let touchedFiles = 0;
let replacements = 0;
for (const file of files) {
  let content = readFileSync(file, "utf8");
  const before = content;
  for (const [from, to] of mapping) {
    const next = replaceToken(content, from, to);
    if (next !== content) {
      replacements += countMatches(content, from);
      content = next;
    }
  }
  if (content !== before) {
    writeFileSync(file, content);
    touchedFiles += 1;
  }
}

mkdirSync(manifestDir, { recursive: true });
writeFileSync(join(manifestDir, "class-map.json"), JSON.stringify({
  generated_at: new Date().toISOString(),
  mode: "post-build-token-rewrite",
  note: "Tailwind utility class adları bilerek değiştirilmez; sadece KGM özel class tokenları hashlenir.",
  mapped_count: mapping.size,
  touched_files: touchedFiles,
  replacements,
  mapping: Object.fromEntries(mapping),
}, null, 2));

console.log(`KGM hardening tamamlandı: ${mapping.size} class map, ${touchedFiles} dosya, ${replacements} değişim.`);

function collectClassCandidates(roots) {
  const names = new Set();
  for (const file of roots.flatMap((dir) => existsSync(dir) ? walk(dir) : [])) {
    if (!/\.(?:tsx|ts|jsx|js|css)$/i.test(file)) continue;
    const content = readFileSync(file, "utf8");
    if (/\.css$/i.test(file)) {
      collectCssClassSelectors(content, names);
    } else {
      collectClassNameLiterals(content, names);
    }
  }
  return [...names].sort((a, b) => b.length - a.length || a.localeCompare(b));
}

function collectBuildClassCandidates(dir) {
  const names = new Set();
  for (const file of walk(dir).filter((file) => /\.(?:js|css|html|json|rsc)$/i.test(file))) {
    const content = readFileSync(file, "utf8");
    if (/\.css$/i.test(file)) {
      collectCssClassSelectors(content, names);
      continue;
    }
    collectClassAttributes(content, names);
  }
  return [...names];
}

function collectCssClassSelectors(content, names) {
  const prefixPattern = protectedPrefixes.map(escapeRegExp).join("|");
  const re = new RegExp(`\\.((?:${prefixPattern})[A-Za-z0-9_-]*)`, "g");
  for (const match of content.matchAll(re)) addIfProtectedClass(match[1], names);
}

function collectClassNameLiterals(content, names) {
  const re = /className\s*=\s*(?:"([^"]*)"|'([^']*)'|{`([^`]+)`})/g;
  for (const match of content.matchAll(re)) {
    const value = match[1] ?? match[2] ?? match[3] ?? "";
    collectClassTokens(value, names);
  }
}

function collectClassAttributes(content, names) {
  const htmlClassRe = /class(?:Name)?=["']([^"']*)["']/g;
  const jsClassRe = /className["']?\s*[:=]\s*["']([^"']*)["']/g;

  for (const match of content.matchAll(htmlClassRe)) {
    collectClassTokens(match[1] ?? "", names);
  }
  for (const match of content.matchAll(jsClassRe)) {
    collectClassTokens(match[1] ?? "", names);
  }
}

function collectClassTokens(value, names) {
  const prefixPattern = protectedPrefixes.map(escapeRegExp).join("|");
  const classTokenRe = new RegExp(`(?<![A-Za-z0-9_-])((?:${prefixPattern})[A-Za-z0-9_-]*)`, "g");

  for (const token of value.matchAll(classTokenRe)) {
    addIfProtectedClass(token[1], names);
  }
}

function addIfProtectedClass(value, names) {
  if (!value || value.includes("/") || value.includes(".")) return;
  if (!/^[A-Za-z_-][A-Za-z0-9_-]*$/.test(value)) return;
  if (protectedPrefixes.some((prefix) => value.startsWith(prefix)) && value.length >= 5) {
    names.add(value);
  }
}

function obfuscatedName(name) {
  const hash = createHash("sha256").update(`${salt}:${name}`).digest("base64url").slice(0, 12);
  return `x-${hash}`;
}

function replaceToken(content, from, to) {
  return content.replace(new RegExp(`(?<![A-Za-z0-9_/-])${escapeRegExp(from)}(?![A-Za-z0-9_-])`, "g"), to);
}

function countMatches(content, token) {
  return (content.match(new RegExp(`(?<![A-Za-z0-9_/-])${escapeRegExp(token)}(?![A-Za-z0-9_-])`, "g")) || []).length;
}

function removeSourceMaps(dir) {
  for (const file of walk(dir)) {
    if (/\.map$/i.test(file)) {
      unlinkSync(file);
      continue;
    }
    if (/\.(?:js|css)$/i.test(file)) {
      const content = readFileSync(file, "utf8");
      const cleaned = content.replace(/\n?\/\/# sourceMappingURL=.*$/gm, "").replace(/\n?\/\*# sourceMappingURL=.*?\*\//gm, "");
      if (cleaned !== content) writeFileSync(file, cleaned);
    }
  }
}

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

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
