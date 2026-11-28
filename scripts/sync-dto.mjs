/**
 * Syncs the generated PHP DTO package from the claimbridge-medula-spec
 * repository into generated/. Mirrors the sandbox's sync-bundle norm:
 * the committed copies change only when the spec itself changes.
 *
 * Files are fetched through the git blobs API (not raw CDN) so a freshly
 * pushed ref is never served stale.
 *
 * Usage: node scripts/sync-dto.mjs [--ref main]
 */
const SPEC_REPO = "kefyusuf/claimbridge-medula-spec";
const args = process.argv.slice(2);
const refFlag = args.indexOf("--ref");
const ref = refFlag >= 0 && args[refFlag + 1] ? args[refFlag + 1] : "main";

const headers = { "user-agent": "claimbridge-laravel-client-sync" };

// Unauthenticated API calls are rate-limited to 60/hour; prefer the gh CLI
// token locally and GITHUB_TOKEN on CI.
const token =
  process.env.GH_TOKEN ??
  process.env.GITHUB_TOKEN ??
  (await (async () => {
    try {
      const { execFileSync } = await import("node:child_process");
      return execFileSync("gh", ["auth", "token"], {
        stdio: ["ignore", "pipe", "ignore"],
      })
        .toString()
        .trim();
    } catch {
      return null;
    }
  })());
if (token) {
  headers.authorization = `Bearer ${token}`;
}

async function fetchJson(url) {
  const res = await fetch(url, { headers });
  if (res.status === 403 || res.status === 429) {
    throw new Error(
      `rate limited for ${url} — set GH_TOKEN or GITHUB_TOKEN to raise the limit`,
    );
  }
  if (!res.ok) {
    throw new Error(`fetch failed: HTTP ${res.status} for ${url}`);
  }
  return res.json();
}

async function main() {
  const tree = await fetchJson(
    `https://api.github.com/repos/${SPEC_REPO}/git/trees/${ref}?recursive=1`,
  );
  const entries = (tree.tree ?? []).filter(
    (e) => e.path.startsWith("dist/php/src/") && e.path.endsWith(".php"),
  );
  if (entries.length === 0) {
    throw new Error(`no dist/php files found at ref ${ref}`);
  }

  const { mkdirSync, writeFileSync, rmSync } = await import("node:fs");
  rmSync("generated", { recursive: true, force: true });
  let written = 0;
  for (const entry of entries) {
    const rel = entry.path.replace("dist/php/src/", "");
    const blob = await fetchJson(
      `https://api.github.com/repos/${SPEC_REPO}/git/blobs/${entry.sha}`,
    );
    const content = Buffer.from(blob.content, "base64").toString("utf8");
    const target = `generated/${rel}`;
    mkdirSync(target.slice(0, target.lastIndexOf("/")), { recursive: true });
    writeFileSync(target, content, "utf8");
    written++;
  }
  console.log(
    `synced ${written} generated PHP files from ${SPEC_REPO}@${ref} into generated/`,
  );

  // The pinned spec bundle drives payload validation client-side; it
  // must track the same ref as the generated package.
  const bundleEntry = (tree.tree ?? []).find(
    (e) => e.path === "dist/claimbridge-medula-spec.json",
  );
  if (!bundleEntry) {
    throw new Error(`no dist bundle found at ref ${ref}`);
  }
  const bundleBlob = await fetchJson(
    `https://api.github.com/repos/${SPEC_REPO}/git/blobs/${bundleEntry.sha}`,
  );
  const bundle = Buffer.from(bundleBlob.content, "base64").toString("utf8");
  JSON.parse(bundle); // fail fast on invalid JSON
  writeFileSync("resources/bundle.json", bundle, "utf8");
  console.log(
    `synced resources/bundle.json (spec ${JSON.parse(bundle).specVersion}) from ${SPEC_REPO}@${ref}`,
  );
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
