import { writeFileSync } from 'fs';
import { join } from 'path';
import { config } from 'dotenv';
import axios from 'axios';

// Load environment variables
config();

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://hyper-local-22.vercel.app';
const API_URL = process.env.NEXT_PUBLIC_ADMIN_PANEL_URL;

// Define static routes (excluding user-specific pages)
const staticRoutes = [
    { path: '/', priority: '1.0' },
    { path: '/about-us/', priority: '0.7' },
    { path: '/brands/', priority: '0.8' },
    { path: '/categories/', priority: '0.9' },
    { path: '/delivery-zones/', priority: '0.6' },
    { path: '/faqs/', priority: '0.6' },
    { path: '/feature-sections/', priority: '0.5' },
    { path: '/privacy-policy/', priority: '0.4' },
    { path: '/products/', priority: '0.9' },
    { path: '/return-refund-policy/', priority: '0.4' },
    { path: '/seller-register/', priority: '0.6' },
    { path: '/shipping-policy/', priority: '0.4' },
    { path: '/stores/', priority: '0.8' },
    { path: '/terms-and-conditions/', priority: '0.4' },
];

// Get current date in ISO format
const currentDate = new Date().toISOString().split('T')[0];

// Fetch dynamic routes from API
async function fetchDynamicRoutes() {
    const dynamicRoutes = [];
    let defaultLatitude = null;
    let defaultLongitude = null;

    try {
        console.log('📡 Fetching dynamic routes from API...');

        // Fetch Settings to get default location
        try {
            const settingsRes = await axios.get(`${API_URL}/api/settings`);
            if (settingsRes.data?.success && settingsRes.data?.data) {
                const webSettings = settingsRes.data.data.find(s => s.variable === 'web')?.value;
                if (webSettings?.defaultLatitude && webSettings?.defaultLongitude) {
                    defaultLatitude = webSettings.defaultLatitude;
                    defaultLongitude = webSettings.defaultLongitude;
                    console.log(`   ✓ Default location: ${defaultLatitude}, ${defaultLongitude}`);
                }
            }
        } catch (error) {
            console.log('   ⚠ Could not fetch settings:', error.message);
        }

        // Fetch Categories
        try {
            const categoriesRes = await axios.get(`${API_URL}/api/categories`, {
                params: { per_page: 1000 }
            });
            if (categoriesRes.data?.success && categoriesRes.data?.data?.data) {
                categoriesRes.data.data.data.forEach(category => {
                    if (category.slug) {
                        dynamicRoutes.push({
                            path: `/categories/${category.slug}/`,
                            priority: '0.7'
                        });
                    }
                });
                console.log(`   ✓ Found ${categoriesRes.data.data.data.length} categories`);
            }
        } catch (error) {
            console.log('   ⚠ Could not fetch categories:', error.message);
        }

        // Fetch Brands
        try {
            const brandsRes = await axios.get(`${API_URL}/api/brands`, {
                params: { per_page: 1000 }
            });
            if (brandsRes.data?.success && brandsRes.data?.data?.data) {
                brandsRes.data.data.data.forEach(brand => {
                    if (brand.slug) {
                        dynamicRoutes.push({
                            path: `/brands/${brand.slug}/`,
                            priority: '0.6'
                        });
                    }
                });
                console.log(`   ✓ Found ${brandsRes.data.data.data.length} brands`);
            }
        } catch (error) {
            console.log('   ⚠ Could not fetch brands:', error.message);
        }

        // Fetch Stores
        try {
            const storesRes = await axios.get(`${API_URL}/api/stores`, {
                params: { per_page: 1000 }
            });
            if (storesRes.data?.success && storesRes.data?.data?.data) {
                storesRes.data.data.data.forEach(store => {
                    if (store.slug) {
                        dynamicRoutes.push({
                            path: `/stores/${store.slug}/`,
                            priority: '0.7'
                        });
                    }
                });
                console.log(`   ✓ Found ${storesRes.data.data.data.length} stores`);
            }
        } catch (error) {
            console.log('   ⚠ Could not fetch stores:', error.message);
        }

        // Fetch Products (note: may fail if coordinates don't match a delivery zone)
        if (defaultLatitude && defaultLongitude) {
            try {
                const productsRes = await axios.get(`${API_URL}/api/delivery-zone/products`, {
                    params: {
                        per_page: 100,
                        latitude: defaultLatitude,
                        longitude: defaultLongitude
                    },
                    timeout: 10000 // 10 second timeout
                });

                if (productsRes.data?.success && productsRes.data?.data?.data) {
                    productsRes.data.data.data.forEach(product => {
                        if (product.slug) {
                            dynamicRoutes.push({
                                path: `/products/${product.slug}/`,
                                priority: '0.8'
                            });
                        }
                    });
                    console.log(`   ✓ Found ${productsRes.data.data.data.length} products`);
                }
            } catch (error) {
                const errorMsg = error.response?.status
                    ? `HTTP ${error.response.status}`
                    : error.message;
                console.log(`   ⚠ Could not fetch products: ${errorMsg}`);
                console.log('   ℹ Products require valid delivery zone coordinates');
            }
        } else {
            console.log('   ⚠ Skipping products: No default location found in settings');
        }

    } catch (error) {
        console.log('⚠️  Error fetching dynamic routes:', error.message);
    }

    return dynamicRoutes;
}

// Generate sitemap XML
function generateSitemap(routes) {
    const urlEntries = routes.map(route => `  <url>
    <loc>${SITE_URL}${route.path}</loc>
    <lastmod>${currentDate}</lastmod>
    <priority>${route.priority}</priority>
  </url>`).join('\n\n');

    return `<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urlEntries}
</urlset>`;
}

// Main function
async function main() {
    console.log('🚀 Generating sitemap...\n');

    // Combine static and dynamic routes
    const dynamicRoutes = await fetchDynamicRoutes();
    const allRoutes = [...staticRoutes, ...dynamicRoutes];

    // Generate sitemap
    const sitemap = generateSitemap(allRoutes);
    const sitemapPath = join(process.cwd(), 'public', 'sitemap.xml');

    writeFileSync(sitemapPath, sitemap, 'utf8');

    console.log('\n✅ Sitemap generated successfully!');
    console.log(`   📍 Location: public/sitemap.xml`);
    console.log(`   🌐 Base URL: ${SITE_URL}`);
    console.log(`   📄 Static routes: ${staticRoutes.length}`);
    console.log(`   🔄 Dynamic routes: ${dynamicRoutes.length}`);
    console.log(`   📊 Total URLs: ${allRoutes.length}`);
}

main().catch(error => {
    console.error('❌ Error generating sitemap:', error);
    process.exit(1);
});
