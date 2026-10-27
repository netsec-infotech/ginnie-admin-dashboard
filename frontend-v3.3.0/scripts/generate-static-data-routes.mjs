import {
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  statSync,
  writeFileSync,
} from "node:fs";
import { dirname, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const NEXT_DATA_PATTERN =
  /<script id="__NEXT_DATA__" type="application\/json">([\s\S]*?)<\/script>/;

const walkHtmlFiles = (directory) =>
  readdirSync(directory).flatMap((entry) => {
    const filename = join(directory, entry);
    return statSync(filename).isDirectory()
      ? walkHtmlFiles(filename)
      : filename.endsWith(".html")
        ? [filename]
        : [];
  });

const getServerDataPages = (root, buildManifest) =>
  new Set(
    Object.entries(buildManifest.pages)
      .filter(([, files]) =>
        files.some((file) => {
          if (!file.includes("static/chunks/pages/")) return false;
          const chunk = join(root, ".next", file);
          return existsSync(chunk) && readFileSync(chunk, "utf8").includes("__N_SSP");
        }),
      )
      .map(([page]) => page),
  );

const dataFilenameForHtml = (outDirectory, htmlFilename, buildId) => {
  const htmlPath = relative(outDirectory, htmlFilename).split(sep).join("/");
  const routePath = htmlPath === "index.html"
    ? "index"
    : htmlPath.replace(/\/index\.html$/, "").replace(/\.html$/, "");
  return join(outDirectory, "_next", "data", buildId, `${routePath}.json`);
};

/**
 * Next's compiler marks a page as __N_SSP when it sees a named
 * getServerSideProps export, even when export mode evaluates that binding to
 * undefined. Static export then omits the matching data JSON, so client-side
 * navigation gets a 404 and Next falls back to a full document navigation.
 *
 * Mirror each exported page's embedded props into the data route expected by
 * the client bundle. Existing SSG data is left untouched.
 */
export function generateStaticDataRoutes(root = process.cwd()) {
  const outDirectory = join(root, "out");
  const buildManifestFilename = join(root, ".next", "build-manifest.json");
  if (!existsSync(outDirectory) || !existsSync(buildManifestFilename)) return [];

  const buildManifest = JSON.parse(readFileSync(buildManifestFilename, "utf8"));
  const serverDataPages = getServerDataPages(root, buildManifest);
  const created = [];

  for (const htmlFilename of walkHtmlFiles(outDirectory)) {
    const html = readFileSync(htmlFilename, "utf8");
    const match = html.match(NEXT_DATA_PATTERN);
    if (!match) continue;

    const nextData = JSON.parse(match[1]);
    if (!serverDataPages.has(nextData.page) || !nextData.buildId) continue;

    const dataFilename = dataFilenameForHtml(
      outDirectory,
      htmlFilename,
      nextData.buildId,
    );
    if (existsSync(dataFilename)) continue;

    mkdirSync(dirname(dataFilename), { recursive: true });
    writeFileSync(
      dataFilename,
      JSON.stringify({ ...nextData.props, __N_SSP: true }),
    );
    created.push(relative(root, dataFilename).split(sep).join("/"));
  }

  return created;
}

const isDirectRun =
  process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isDirectRun) {
  const created = generateStaticDataRoutes();
  console.log(`Generated ${created.length} static navigation data route(s).`);
}
