import { promises as fs } from "node:fs";
import path from "node:path";

const projectRoot = process.cwd();
const protectRoots = [
  "php-frontend/public",
  "php-frontend/includes",
  "php-frontend/assets/js",
  "php-frontend/assets/css",
  "php-admin/public",
  "php-admin/includes",
  "php-admin/assets/js",
  "php-admin/assets/css",
];

const protectedMarkers = {
  php: "/* protected-build:php */",
  js: "/* protected-build:js */",
  css: "/* protected-build:css */",
};

const originalContents = new Map();

async function pathExists(targetPath) {
  try {
    await fs.access(targetPath);
    return true;
  } catch {
    return false;
  }
}

async function collectFiles(rootDir) {
  const entries = await fs.readdir(rootDir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(rootDir, entry.name);
    if (entry.isDirectory()) {
      files.push(...(await collectFiles(fullPath)));
      continue;
    }

    files.push(fullPath);
  }

  return files;
}

function isProtected(content, extension) {
  if (extension === ".php") {
    return content.includes(protectedMarkers.php) && content.includes("base64_decode(");
  }

  if (extension === ".js") {
    const jsProtection = getJsProtectionInfo(content);
    return (
      content.includes(protectedMarkers.js) &&
      content.includes('TextDecoder("utf-8")') &&
      jsProtection.layers === 1 &&
      !jsProtection.source.includes(protectedMarkers.js)
    );
  }

  if (extension === ".css") {
    return content.includes(protectedMarkers.css) && content.includes("data:text/css;base64,");
  }

  return false;
}

function minifyCss(input) {
  return input
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .replace(/\s+/g, " ")
    .replace(/\s*([{}:;,>+~])\s*/g, "$1")
    .replace(/;}/g, "}")
    .trim();
}

function resolveCssImports(input) {
  return input.replace(/@import\s+url\((['"]?)([^'")]+)\1\)\s*;/g, (_match, _quote, importPath) => {
    if (importPath === "/assets/css/styles.css") {
      const sharedPath = path.join(projectRoot, "php-frontend/assets/css/styles.css");
      return originalContents.get(sharedPath) ?? "";
    }

    return "";
  });
}

function protectPhp(input) {
  const payload = Buffer.from(input, "utf8").toString("base64");
  return `<?php ${protectedMarkers.php} $__payload='${payload}';eval('?>'.base64_decode($__payload));unset($__payload);`;
}

function getJsProtectionInfo(input) {
  let current = input;
  let depth = 0;

  while (current.includes(protectedMarkers.js) && depth < 10) {
    const payloadMatch = current.match(/const \$__payload="([^"]+)";/);
    if (!payloadMatch) {
      break;
    }

    current = Buffer.from(payloadMatch[1], "base64").toString("utf8");
    depth += 1;
  }

  return {
    source: current,
    layers: depth,
  };
}

function unwrapProtectedJs(input) {
  return getJsProtectionInfo(input).source;
}

function protectJs(input) {
  const payload = Buffer.from(input, "utf8").toString("base64");
  return `${protectedMarkers.js}(()=>{const $__payload="${payload}";const $__binary=atob($__payload);const $__bytes=Uint8Array.from($__binary,(char)=>char.charCodeAt(0));const $__script=document.createElement("script");$__script.text=new TextDecoder("utf-8").decode($__bytes);(document.head||document.documentElement).appendChild($__script);$__script.remove();})();`;
}

function protectCss(input) {
  const payload = Buffer.from(minifyCss(resolveCssImports(input)), "utf8").toString("base64");
  return `${protectedMarkers.css}@charset "UTF-8";@import url("data:text/css;base64,${payload}");`;
}

async function main() {
  const files = [];

  for (const root of protectRoots) {
    const absoluteRoot = path.join(projectRoot, root);
    if (!(await pathExists(absoluteRoot))) {
      continue;
    }
    files.push(...(await collectFiles(absoluteRoot)));
  }

  for (const file of files) {
    const content = await fs.readFile(file, "utf8");
    originalContents.set(file, content);
  }

  let changedCount = 0;

  for (const file of files) {
    const original = originalContents.get(file);
    const extension = path.extname(file);

    if (!original || isProtected(original, extension)) {
      continue;
    }

    let nextContent = original;

    if (extension === ".php") {
      nextContent = protectPhp(original);
    } else if (extension === ".js") {
      nextContent = protectJs(unwrapProtectedJs(original));
    } else if (extension === ".css") {
      nextContent = protectCss(original);
    } else {
      continue;
    }

    if (nextContent !== original) {
      await fs.writeFile(file, nextContent, "utf8");
      changedCount += 1;
    }
  }

  console.log(`Protected ${changedCount} files.`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
