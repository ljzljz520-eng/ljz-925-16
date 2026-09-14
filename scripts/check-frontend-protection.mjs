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

const protectedMatchers = {
  ".php": (content) => content.includes("/* protected-build:php */") && content.includes("base64_decode("),
  ".js": (content) => {
    const jsProtection = getJsProtectionInfo(content);
    return (
      content.includes("/* protected-build:js */") &&
      content.includes('TextDecoder("utf-8")') &&
      jsProtection.layers === 1 &&
      !jsProtection.source.includes("/* protected-build:js */")
    );
  },
  ".css": (content) => content.includes("/* protected-build:css */") && content.includes("data:text/css;base64,"),
};

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

function getJsProtectionInfo(input) {
  let current = input;
  let depth = 0;

  while (current.includes("/* protected-build:js */") && depth < 10) {
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

async function main() {
  const invalidFiles = [];

  for (const root of protectRoots) {
    const absoluteRoot = path.join(projectRoot, root);
    if (!(await pathExists(absoluteRoot))) {
      continue;
    }
    const files = await collectFiles(absoluteRoot);

    for (const file of files) {
      const extension = path.extname(file);
      const matcher = protectedMatchers[extension];

      if (!matcher) {
        continue;
      }

      const content = await fs.readFile(file, "utf8");
      if (!matcher(content)) {
        invalidFiles.push(path.relative(projectRoot, file));
      }
    }
  }

  if (invalidFiles.length > 0) {
    console.error("以下前端文件仍未保护：");
    for (const file of invalidFiles) {
      console.error(`- ${file}`);
    }
    process.exitCode = 1;
    return;
  }

  console.log("所有前端 PHP/JS/CSS 文件均已处于保护状态。");
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
