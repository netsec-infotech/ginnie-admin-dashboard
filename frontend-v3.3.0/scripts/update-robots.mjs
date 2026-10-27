import { readFileSync, writeFileSync } from "fs";
import { join } from "path";
import dotenv from "dotenv";

dotenv.config();

const rawSiteUrl = process.env.NEXT_PUBLIC_SITE_URL?.trim();
if (!rawSiteUrl) {
  console.error("❌ NEXT_PUBLIC_SITE_URL is required to generate robots.txt");
  process.exit(1);
}

try {
  const siteUrl = new URL(rawSiteUrl);
  if (!/^https?:$/.test(siteUrl.protocol)) {
    throw new Error("NEXT_PUBLIC_SITE_URL must use http or https");
  }

  const templatePath = join(process.cwd(), "scripts", "robots.template.txt");
  const outputPath = join(process.cwd(), "public", "robots.txt");
  const baseUrl = `${siteUrl.origin}${siteUrl.pathname.replace(/\/+$/, "")}`;
  const robotsContent = readFileSync(templatePath, "utf8").replaceAll(
    "{{SITE_URL}}",
    baseUrl,
  );

  writeFileSync(outputPath, robotsContent, "utf8");
  console.log("✅ robots.txt updated successfully");
  console.log(`   Site URL: ${baseUrl}`);
} catch (error) {
  console.error("❌ Error updating robots.txt:", error.message);
  process.exit(1);
}
