<?php

namespace Database\Seeders;

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Product;
use App\Models\WatchBuyProfile;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get or ensure the single official Admin Platform Watch & Buy profile
        $adminProfile = $this->getAdminProfile();

        // 2. Seed / Ensure Blog Categories
        $categories = $this->seedBlogCategories();

        // 3. Retrieve available products for linking
        $availableProductIds = Product::query()
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // 4. Seed 18 Curated, High-Quality Blog Posts
        $blogsData = $this->getBlogsData($adminProfile, $categories, $availableProductIds);

        foreach ($blogsData as $data) {
            $imageUrl = $data['image_url'] ?? null;
            unset($data['image_url']);

            $blog = Blog::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            // Optionally attach cover image if not already present
            if ($imageUrl && ! $blog->hasMedia(SpatieMediaCollectionName::BLOG_IMAGE())) {
                try {
                    $blog->addMediaFromUrl($imageUrl)
                        ->toMediaCollection(SpatieMediaCollectionName::BLOG_IMAGE());
                } catch (\Throwable) {
                    // Graceful fallback if offline or request fails
                }
            }
        }
    }

    /**
     * Get or ensure the single official Admin/Platform author profile.
     */
    protected function getAdminProfile(): WatchBuyProfile
    {
        $profile = WatchBuyProfile::query()
            ->where('profile_type', WatchBuyProfileTypeEnum::PLATFORM)
            ->where('status', 'active')
            ->first();

        if (! $profile) {
            $profile = WatchBuyProfile::updateOrCreate(
                ['username' => 'hyperlocal'],
                [
                    'profile_type' => WatchBuyProfileTypeEnum::PLATFORM,
                    'bio' => 'Official Admin Profile',
                    'status' => WatchBuyProfileStatusEnum::ACTIVE,
                ]
            );
        }

        return $profile;
    }

    /**
     * Seed foundational blog categories with SEO metadata.
     *
     * @return array<string, BlogCategory>
     */
    protected function seedBlogCategories(): array
    {
        $categories = [
            'recipes' => [
                'name' => 'Recipes & Cooking',
                'slug' => 'recipes-and-cooking',
                'description' => 'Delicious, wholesome recipes crafted with fresh farm produce and kitchen staples.',
                'sort_order' => 1,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Healthy & Delicious Cooking Recipes | Hyperlocal Blog',
                    'seo_keywords' => ['recipes', 'cooking', 'easy dinners', 'home cooking', 'fresh meals'],
                    'seo_description' => 'Explore step-by-step gourmet and quick weeknight recipes made with fresh local ingredients.',
                ],
            ],
            'wellness' => [
                'name' => 'Health & Wellness',
                'slug' => 'health-and-wellness',
                'description' => 'Holistic advice, mindful living guides, and nutritionist-backed wellness tips.',
                'sort_order' => 2,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Health, Nutrition & Mindful Living Tips | Hyperlocal',
                    'seo_keywords' => ['wellness', 'healthy habits', 'nutrition', 'immunity', 'clean eating'],
                    'seo_description' => 'Expert tips on boosting energy, improving sleep, and living a balanced healthy life.',
                ],
            ],
            'organic' => [
                'name' => 'Organic & Fresh Produce',
                'slug' => 'organic-and-fresh-produce',
                'description' => 'Guides on seasonal farming, organic certifications, and farm-to-table groceries.',
                'sort_order' => 3,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Organic Farming & Fresh Produce Guide | Hyperlocal',
                    'seo_keywords' => ['organic groceries', 'pesticide-free', 'fresh fruits', 'local farmers'],
                    'seo_description' => 'Learn how local farmers harvest chemical-free vegetables and seasonal fruits.',
                ],
            ],
            'kitchen_hacks' => [
                'name' => 'Kitchen Tips & Hacks',
                'slug' => 'kitchen-tips-and-hacks',
                'description' => 'Smart food storage ideas, zero-waste kitchen tricks, and culinary techniques.',
                'sort_order' => 4,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Smart Kitchen Tips, Food Storage & Culinary Hacks',
                    'seo_keywords' => ['kitchen hacks', 'food storage', 'meal prep tips', 'cooking techniques'],
                    'seo_description' => 'Save time and keep vegetables fresh longer with our proven kitchen tips.',
                ],
            ],
            'seasonal' => [
                'name' => 'Seasonal & Local Guides',
                'slug' => 'seasonal-and-local-guides',
                'description' => 'Celebrating local harvests, seasonal superfoods, and festive dining traditions.',
                'sort_order' => 5,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Seasonal Eating & Hyperlocal Food Guides',
                    'seo_keywords' => ['seasonal produce', 'hyperlocal food', 'summer fruits', 'winter immunity'],
                    'seo_description' => 'Discover what fruits and vegetables are in season right now in your locality.',
                ],
            ],
            'superfoods' => [
                'name' => 'Diet & Superfoods',
                'slug' => 'diet-and-superfoods',
                'description' => 'Nutrient-dense superfoods, high-protein plant diets, and ancient healing herbs.',
                'sort_order' => 6,
                'status' => true,
                'metadata' => [
                    'seo_title' => 'Superfoods, High-Protein Diets & Ancient Herbs Guide',
                    'seo_keywords' => ['superfoods', 'protein diet', 'chia seeds', 'turmeric', 'antioxidants'],
                    'seo_description' => 'Unlock the science-backed health benefits of superfoods and functional nutrition.',
                ],
            ],
        ];

        $seededCategories = [];
        foreach ($categories as $key => $categoryData) {
            $seededCategories[$key] = BlogCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }

        return $seededCategories;
    }

    /**
     * Get 18 comprehensive blog article definitions using the Admin profile.
     *
     * @param  array<string, BlogCategory>  $categories
     * @param  array<int>  $productIds
     * @return array<int, array<string, mixed>>
     */
    protected function getBlogsData(WatchBuyProfile $adminProfile, array $categories, array $productIds): array
    {
        $pickProducts = function (int $count = 2) use ($productIds): array {
            if (empty($productIds)) {
                return [];
            }
            $shuffled = $productIds;
            shuffle($shuffled);

            return array_slice($shuffled, 0, min($count, count($productIds)));
        };

        return [
            // Blog 1
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['recipes']->id, $categories['kitchen_hacks']->id],
                'title' => '10 Quick & Nutritious 15-Minute Dinner Recipes for Busy Weekdays',
                'slug' => '10-quick-nutritious-15-minute-dinner-recipes',
                'short_description' => 'Say goodbye to unhealthy takeout! These 10 lightning-fast dinners use fresh pantry staples and take under 15 minutes from pan to plate.',
                'content' => '<h2>Fast, Wholesome Eating for Modern Schedules</h2>
<p>After a demanding day at work, the last thing anyone wants is spending two hours over a hot stove. However, turning to greasy takeout every evening takes a toll on both your wallet and your long-term vitality. The secret to 15-minute dinners lies in smart ingredient pairing and quick-cooking whole foods.</p>
<h3>1. Garlic-Chili Tossed Zucchini Ribbons</h3>
<p>Using a simple vegetable peeler, turn fresh zucchini into nutrient-packed ribbons. Sauté in cold-pressed extra virgin olive oil with minced garlic, crushed red pepper flakes, and top with toasted pine nuts and grated parmesan.</p>
<h3>2. Spiced Chickpea & Spinach Sauté</h3>
<p>Drain a jar of organic chickpeas, toss them into a piping hot skillet with cumin, smoked paprika, and baby spinach leaves until wilted. Squeeze fresh lemon juice over the top for an instant protein boost.</p>
<blockquote><p><strong>Chef’s Secret Tip:</strong> Always keep pre-washed greens and whole spices within arm\'s reach. Pre-prepping aromatic bases cuts cooking time by half!</p></blockquote>
<h3>3. Pan-Seared Salmon with Herb Butter</h3>
<p>Salmon fillets cook in just 4 minutes per side. Baste with fresh rosemary and grass-fed butter for a rich, omega-3 loaded centerpiece.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['quick-meals', 'dinner', 'healthy-eating', 'easy-recipes', '15-minute-meals'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(2),
                'views_count' => 1240,
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => '10 Quick 15-Minute Healthy Dinner Recipes | Hyperlocal Food',
                    'seo_keywords' => ['15-minute dinners', 'easy dinner recipes', 'healthy weeknight meals', 'quick cooking'],
                    'seo_description' => 'Discover 10 delicious and nutritious 15-minute dinner recipes made with fresh pantry groceries.',
                ],
            ],

            // Blog 2
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['kitchen_hacks']->id, $categories['organic']->id],
                'title' => 'The Ultimate Guide to Storing Fresh Vegetables & Leafy Greens',
                'slug' => 'ultimate-guide-storing-fresh-vegetables-herbs',
                'short_description' => 'Tired of herbs turning slimy and greens wilting within 48 hours? Learn the exact humidity, temperature, and wrapping techniques to double produce shelf life.',
                'content' => '<h2>Stop Wasting Fresh Farm Produce</h2>
<p>Freshly harvested organic vegetables contain vibrant enzymes, vitamins, and minerals. However, improper post-harvest storage can cause over 40% of fresh produce to spoil prematurely. Understanding which vegetables love moisture and which require dry ventilation is key.</p>
<h3>The Paper Towel Trick for Leafy Greens</h3>
<p>Excess moisture is the enemy of delicate leaves like spinach, arugula, and coriander. Line an airtight glass container with a dry, unbleached paper towel, place dry greens loosely inside, and seal. The towel absorbs condensation while keeping ambient humidity ideal.</p>
<h3>Treat Herbs Like Fresh Flowers</h3>
<p>Tender stem herbs such as mint, basil, and parsley should have their stems trimmed and placed into a small glass of water at room temperature or inside the refrigerator door (except basil, which prefers room temperature).</p>
<h3>Keep Ethylene Gas Producers Away</h3>
<p>Apples, bananas, and tomatoes produce natural ethylene gas as they ripen, which accelerates spoilage of neighboring cucumbers, potatoes, and broccoli. Store them in separate baskets!</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['kitchen-hacks', 'fresh-produce', 'food-storage', 'zero-waste', 'organic-tips'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(4),
                'views_count' => 890,
                'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'How to Store Vegetables & Greens to Keep Fresh Longer | Food Storage Guide',
                    'seo_keywords' => ['vegetable storage', 'keep greens fresh', 'food waste hacks', 'produce preservation'],
                    'seo_description' => 'Master the best techniques to store organic vegetables, herbs, and leafy greens for maximum freshness.',
                ],
            ],

            // Blog 3
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['wellness']->id, $categories['superfoods']->id],
                'title' => 'Why Cold-Pressed Oils Are Better for Everyday Cooking',
                'slug' => 'why-cold-pressed-oils-are-better-for-cooking',
                'short_description' => 'Discover how traditional cold-pressed extraction preserves polyphenols, vitamin E, and natural fatty acids compared to refined commercial oils.',
                'content' => '<h2>Understanding Traditional Oil Extraction</h2>
<p>Cooking oil is the cornerstone of almost every meal we prepare. Yet, refined commercial oils undergo heavy industrial processing—including solvent extraction at temperatures exceeding 200°C, chemical bleaching, and deodorizing—stripping away essential micronutrients.</p>
<h3>What Makes Cold-Pressed (Kachi Ghani) Different?</h3>
<p>Cold-pressed extraction relies solely on mechanical stone or wooden presses at ambient temperatures below 45°C. No chemical solvents like hexane are added. The result is pure, unadulterated oil retaining its natural aroma, golden color, and dense antioxidant profile.</p>
<ul>
<li><strong>High Vitamin E Content:</strong> Acts as a powerful free-radical scavenger supporting skin and cardiovascular cellular health.</li>
<li><strong>Intact Polyphenols:</strong> Naturally fights systemic inflammation throughout the digestive tract.</li>
<li><strong>Balanced Fatty Acids:</strong> Provides wholesome ratios of Omega-3, 6, and 9 essential lipids.</li>
</ul>
<blockquote><p><strong>Nutritionist Note:</strong> Use cold-pressed mustard or sesame oil for high-heat sautéing, and cold-pressed extra virgin olive oil for salads and finishing drizzles.</p></blockquote>',
                'product_ids' => $pickProducts(2),
                'tags' => ['organic', 'cold-pressed', 'wellness', 'healthy-fats', 'nutrition'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(6),
                'views_count' => 1560,
                'image_url' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Cold-Pressed Oils vs Refined Oils: Health Benefits & Guide',
                    'seo_keywords' => ['cold-pressed oil', 'kachi ghani', 'healthy cooking oils', 'antioxidants'],
                    'seo_description' => 'Learn why cold-pressed unrefined oils are superior for cardiovascular health and nutritious cooking.',
                ],
            ],

            // Blog 4
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['wellness']->id, $categories['superfoods']->id],
                'title' => 'How to Build a High-Protein Vegetarian Meal Plan',
                'slug' => 'how-to-build-high-protein-vegetarian-meal-plan',
                'short_description' => 'Hitting 80g-120g of clean protein daily on a vegetarian diet is easier than you think. Here is your complete grocery roadmap.',
                'content' => '<h2>Busting the Vegetarian Protein Myth</h2>
<p>One of the most persistent fitness myths is that a vegetarian diet cannot provide adequate high-quality protein for lean muscle maintenance and cellular recovery. In reality, combining diverse legumes, seeds, dairy, and ancient grains easily covers all 9 essential amino acids.</p>
<h3>Top Plant & Vegetarian Protein Powerhouses</h3>
<ul>
<li><strong>Paneer & Greek Yogurt:</strong> Delivers 18g–22g of slow-digesting casein protein per 100g serving.</li>
<li><strong>Lentils & Chickpeas:</strong> Pack 9g of protein per cooked cup along with prebiotic fiber.</li>
<li><strong>Tofu & Tempeh:</strong> Complete protein sources containing all essential amino acids.</li>
<li><strong>Hemp & Chia Seeds:</strong> Perfect for blending into morning oatmeal or shakes (10g protein per 3 tbsp).</li>
<li><strong>Quinoa:</strong> A gluten-free ancient seed with 8g complete protein per cooked cup.</li>
</ul>
<h3>Sample 100g Daily Protein Blueprint</h3>
<p>Breakfast: Spiced tofu scramble with whole-grain toast (24g)<br>Lunch: Brown rice with thick mixed dal and cucumber raita (28g)<br>Snack: Roasted edamame and walnuts (18g)<br>Dinner: Grilled paneer with sautéed broccoli and quinoa (32g)</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['high-protein', 'vegetarian', 'fitness-diet', 'meal-plan', 'nutrition'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(8),
                'views_count' => 1120,
                'image_url' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Complete High-Protein Vegetarian Meal Plan | Nutrition Guide',
                    'seo_keywords' => ['vegetarian protein', 'high protein diet', 'plant based protein', 'meal plan'],
                    'seo_description' => 'Explore the complete guide to hitting optimal daily protein intake on a vegetarian and plant-rich diet.',
                ],
            ],

            // Blog 5
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['seasonal']->id, $categories['organic']->id],
                'title' => 'Seasonal Summer Fruits: Health Benefits & Refreshing Culinary Ideas',
                'slug' => 'seasonal-summer-fruits-health-benefits-ideas',
                'short_description' => 'From sun-ripened Alphonso mangoes to hydrating watermelons and crisp litchis, explore the peak flavors of summer harvest.',
                'content' => '<h2>Nature’s Hydration Pharmacy</h2>
<p>When the summer heat peaks, nature naturally provides fruits with high electrolyte concentrations, water content, and bioflavonoids. Eating seasonally ensures you consume produce when nutrient density and natural sweetness are at their absolute maximum.</p>
<h3>1. King of Fruits: Raw & Ripe Mangoes</h3>
<p>Rich in vitamins A and C, mangoes also contain mangiferin, an exceptional natural antioxidant. Try raw green mango drinks (Aam Panna) with roasted cumin to cool down body temperature.</p>
<h3>2. Watermelon & Muskmelon</h3>
<p>Consisting of over 92% structured cellular water, melons provide potassium, magnesium, and lycopene—protecting skin cells against intense ultraviolet radiation.</p>
<h3>3. Berries & Jamun (Black Plum)</h3>
<p>Jamun is famous for its low glycemic index and anthocyanin pigments, supporting balanced blood sugar levels throughout warm months.</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['summer-fruits', 'antioxidants', 'hydration', 'seasonal-eating', 'mangoes'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(10),
                'views_count' => 1430,
                'image_url' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Top Summer Fruits & Natural Hydration Recipes | Seasonal Guide',
                    'seo_keywords' => ['summer fruits', 'watermelon benefits', 'mango recipes', 'seasonal eating'],
                    'seo_description' => 'Explore the top seasonal summer fruits and refreshing farm-to-table recipes to stay hydrated.',
                ],
            ],

            // Blog 6
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['kitchen_hacks']->id, $categories['recipes']->id],
                'title' => '5 Common Mistakes People Make When Cooking Rice (And How to Fix Them)',
                'slug' => '5-common-mistakes-cooking-rice-how-to-fix',
                'short_description' => 'Never end up with mushy, clumpy, or burnt rice again. Master the golden water ratio, rinsing techniques, and resting periods.',
                'content' => '<h2>Achieving Fluffy, Restaurant-Quality Rice Every Single Time</h2>
<p>Rice is a staple for billions of households, yet cooking perfectly separated, fluffy grains remains an elusive art for many home cooks. Fortunately, mastering rice comes down to avoiding five simple mechanical mistakes.</p>
<h3>Mistake 1: Skipping the Starch Rinse</h3>
<p>Always wash raw rice under cold water 3 to 4 times until the runoff turns clear. This washes away loose surface starch that otherwise causes grains to clump into a gluey mass.</p>
<h3>Mistake 2: Incorrect Water-to-Rice Ratios</h3>
<p>For aged long-grain Basmati, use a 1:1.5 ratio. For Jasmine or short-grain sushi rice, stick to 1:1.25. Too much water inevitably leads to over-hydrated, broken grains.</p>
<h3>Mistake 3: Lifting the Lid During Simmering</h3>
<p>Resist the urge to peek! Every time the lid is lifted, crucial steam escapes, dropping the internal cooking temperature and resulting in unevenly cooked centers.</p>
<h3>Mistake 4: Forgetting the 10-Minute Rest</h3>
<p>Once cooking is complete, turn off heat and let the pot sit undisturbed with the lid on for 10 minutes. This allows moisture to redistribute evenly before gently fluffing with a fork.</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['cooking-tips', 'rice', 'kitchen-basics', 'chef-hacks', 'basmati'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(12),
                'views_count' => 760,
                'image_url' => 'https://images.unsplash.com/photo-1536304993881-ff6e9eefa2a6?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'How to Cook Perfect Fluffy Rice: 5 Common Mistakes Fixed',
                    'seo_keywords' => ['cook rice', 'fluffy basmati', 'cooking rice mistakes', 'kitchen tips'],
                    'seo_description' => 'Learn how to cook perfectly fluffy, non-sticky rice every time with professional culinary techniques.',
                ],
            ],

            // Blog 7
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['organic']->id],
                'title' => 'Understanding Organic Certifications: What Food Labels Really Mean',
                'slug' => 'understanding-organic-certifications-food-labels',
                'short_description' => 'Demystifying food label claims like "100% Organic", "Naturally Grown", "Non-GMO", and "Pesticide-Free" for smarter grocery shopping.',
                'content' => '<h2>How to Be a Discerning Organic Grocery Shopper</h2>
<p>Walking through grocery aisles or browsing digital storefronts, shoppers are bombarded with buzzwords: "All-Natural", "Eco-Grown", "Farm-Fresh", and "Certified Organic". But what do these regulatory terms actually guarantee?</p>
<h3>Certified Organic vs Natural Claims</h3>
<p>Unlike marketing terms such as "All-Natural" (which has no strict legal oversight), <strong>Certified Organic</strong> requires third-party auditing, verified soil testing, zero synthetic chemical pesticides, zero synthetic fertilizers, and non-GMO lineage for at least 3 consecutive harvest seasons.</p>
<h3>The Difference Between Hydroponic and Soil-Grown</h3>
<p>While hydroponics can produce clean crops without pesticides, rich organic soil yields produce infused with microbial biodiversity and trace minerals like selenium, zinc, and magnesium.</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['organic-facts', 'shopping-guide', 'eco-friendly', 'food-labels', 'sustainability'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(14),
                'views_count' => 620,
                'image_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'What Do Organic Food Labels Mean? Grocery Shopping Guide',
                    'seo_keywords' => ['organic certification', 'food labels explained', 'non-gmo vs organic', 'grocery guide'],
                    'seo_description' => 'A clear guide to understanding organic certifications and labels for conscious grocery shopping.',
                ],
            ],

            // Blog 8
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['wellness']->id, $categories['superfoods']->id],
                'title' => 'The Healing Powers of Turmeric, Fresh Ginger, and Raw Forest Honey',
                'slug' => 'healing-powers-turmeric-ginger-raw-honey',
                'short_description' => 'How ancient Ayurvedic golden tonics and natural bio-compounds activate immunity, calm inflammation, and restore digestive harmony.',
                'content' => '<h2>Ancient Botanical Synergy Backed by Modern Science</h2>
<p>For thousands of years, traditional herbal medicine has paired golden turmeric with pungent ginger root and unpasteurized honey. Today, modern biochemistry confirms that these three ingredients create a synergistic therapeutic effect.</p>
<h3>1. Curcumin & Piperine Absorption</h3>
<p>Turmeric’s active compound, <strong>curcumin</strong>, is an extraordinary anti-inflammatory agent. However, on its own, it has low bioavailability. Pairing turmeric with a pinch of fresh black pepper (piperine) increases curcumin absorption by up to 2,000%.</p>
<h3>2. Gingerols for Digestive Motility</h3>
<p>Fresh ginger root contains gingerols and shogaols, which accelerate gastric emptying and soothe gastrointestinal discomfort.</p>
<h3>3. Raw Honey as an Enzymatic Carrier</h3>
<p>Unlike commercial corn-syrup blends, raw unheated forest honey contains live enzymes, propolis, and natural antimicrobial polyphenols.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['immunity', 'ayurveda', 'superfoods', 'turmeric', 'ginger', 'wellness'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(16),
                'views_count' => 1840,
                'image_url' => 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Turmeric, Ginger & Raw Honey: Immunity & Health Benefits',
                    'seo_keywords' => ['turmeric benefits', 'golden milk', 'ginger tea', 'raw honey health', 'immunity booster'],
                    'seo_description' => 'Discover how turmeric, fresh ginger, and raw honey boost immunity and reduce systemic inflammation.',
                ],
            ],

            // Blog 9
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['seasonal']->id, $categories['organic']->id],
                'title' => 'Farm-to-Fork: How Hyperlocal Sourcing Transforms Your Food Economy',
                'slug' => 'farm-to-fork-hyperlocal-sourcing-community',
                'short_description' => 'When produce travels less than 50 kilometers from farm to kitchen, nutritional density rises, food miles plummet, and local farmers thrive.',
                'content' => '<h2>Why Distance Matters in Food Quality</h2>
<p>Traditional industrial food supply chains keep vegetables in refrigerated transit for days or even weeks. By the time a head of broccoli reaches a conventional supermarket shelf, it may have lost up to 50% of its vitamin C content.</p>
<h3>The Hyperlocal Advantage</h3>
<ul>
<li><strong>Harvested at True Ripeness:</strong> Produce ripens on the vine under natural sunlight rather than in ethylene gas chambers.</li>
<li><strong>Direct Farmer Prosperity:</strong> Hyperlocal trade cuts out 3–4 layers of middlemen, ensuring local growers receive fair compensation.</li>
<li><strong>Near-Zero Carbon Transport:</strong> Slashing thousands of transit kilometers dramatically shrinks the carbon footprint of your daily grocery basket.</li>
</ul>',
                'product_ids' => $pickProducts(1),
                'tags' => ['hyperlocal', 'sustainability', 'farmers', 'farm-to-table', 'fresh-produce'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(18),
                'views_count' => 540,
                'image_url' => 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'What is Hyperlocal Farm-to-Fork Grocery Sourcing? | Snazi Living',
                    'seo_keywords' => ['hyperlocal food', 'farm to fork', 'local agriculture', 'sustainable grocery'],
                    'seo_description' => 'Learn how hyperlocal food sourcing connects consumers with fresh local harvests while supporting farmers.',
                ],
            ],

            // Blog 10
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['recipes']->id],
                'title' => 'Creamy Garlic Herb Pasta: A Restaurant-Style Dish in 20 Minutes',
                'slug' => 'creamy-garlic-herb-pasta-restaurant-style',
                'short_description' => 'Silky, aromatic, and loaded with fresh garden herbs. Here is the definitive guide to restaurant-grade pasta without heavy processed cream.',
                'content' => '<h2>The Art of the Emulsified Pasta Sauce</h2>
<p>Italian chefs rarely use heavy industrial creams to make creamy pasta. Instead, the magic happens when starchy pasta cooking water emulsifies with quality olive oil, garlic, and freshly grated aged cheese.</p>
<h3>Ingredients You’ll Need</h3>
<ul>
<li>350g Artisanal bronze-cut Fettuccine or Rigatoni</li>
<li>6 Cloves fresh garlic, finely sliced into paper-thin slivers</li>
<li>1/4 Cup Extra Virgin Olive Oil</li>
<li>1 Cup Fresh Italian basil and flat-leaf parsley, finely chopped</li>
<li>1/2 Cup Freshly grated Parmigiano-Reggiano</li>
<li>Freshly ground black peppercorns and sea salt</li>
</ul>
<h3>Step-by-Step Technique</h3>
<p>Boil pasta in well-salted water until 1 minute before al dente. In a large pan, gently sizzle sliced garlic in olive oil over low heat until golden. Add 1/2 cup of starchy pasta water to halt cooking. Transfer pasta into the pan, toss vigorously with cheese and fresh herbs until a glistening, velvety glaze coats every strand.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['pasta', 'italian', 'comfort-food', 'gourmet-cooking', 'recipes'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(20),
                'views_count' => 980,
                'image_url' => 'https://images.unsplash.com/photo-1621996346565-e3d5d6281699?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Creamy Garlic Herb Pasta Recipe | Italian Gourmet Dinners',
                    'seo_keywords' => ['garlic pasta', 'creamy pasta recipe', 'easy italian dinner', 'fresh herbs'],
                    'seo_description' => 'Make authentic restaurant-style creamy garlic herb pasta at home in under 20 minutes.',
                ],
            ],

            // Blog 11
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['wellness']->id, $categories['recipes']->id],
                'title' => 'Detox Smoothies: 5 Energizing Blends for Morning Vitality',
                'slug' => 'detox-smoothies-5-energizing-blends-morning',
                'short_description' => 'Kickstart your metabolism and hydrate your skin with these 5 nutrient-dense green and antioxidant berry smoothie recipes.',
                'content' => '<h2>Recharge Your Morning Routine</h2>
<p>Starting the day with a nutrient-dense whole-food smoothie provides easily absorbable micronutrients without taxing the digestive system. Here are 5 delicious formulas designed for peak morning energy.</p>
<h3>1. The Green Glow Machine</h3>
<p>Blend 1 cup tender baby spinach, 1/2 green apple, 1/2 cucumber, juice of half a lemon, 1 tbsp chia seeds, and 1 cup chilled coconut water.</p>
<h3>2. Berry Antioxidant Shield</h3>
<p>Combine 1/2 cup organic blueberries, 1/2 cup strawberries, 1 tbsp ground flaxseeds, 1 scoop plant protein, and almond milk.</p>
<h3>3. Tropical Turmeric Energizer</h3>
<p>Blend ripe mango slices, fresh pineapple chunks, 1/2 tsp ground turmeric, a pinch of black pepper, and Greek yogurt.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['smoothies', 'detox', 'breakfast', 'clean-eating', 'nutrition'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(22),
                'views_count' => 1350,
                'image_url' => 'https://images.unsplash.com/photo-1553530666-ba11a7da3888?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => '5 Morning Detox Smoothie Recipes for Energy & Glowing Skin',
                    'seo_keywords' => ['detox smoothie', 'green smoothie recipe', 'morning energy drink', 'antioxidants'],
                    'seo_description' => 'Discover 5 energizing morning detox smoothie recipes packed with whole fruits, greens, and seeds.',
                ],
            ],

            // Blog 12
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['kitchen_hacks']->id, $categories['wellness']->id],
                'title' => 'A Beginner’s Guide to Meal Prepping for Busy Working Professionals',
                'slug' => 'beginners-guide-meal-prepping-busy-professionals',
                'short_description' => 'Save 10+ hours every week, reduce grocery bills by 30%, and stay on track with your health goals using batch-cooking techniques.',
                'content' => '<h2>Take Control of Your Weekly Nutrition</h2>
<p>Meal prep doesn’t mean eating monotonous chicken and soggy broccoli for five days straight. The modern approach is <strong>component prepping</strong>—cooking versatile bases that can be mixed and matched into exciting new dishes every day.</p>
<h3>The Component Prep Strategy</h3>
<ul>
<li><strong>Cook 2 Grains:</strong> One batch of brown rice and one batch of fluffy quinoa.</li>
<li><strong>Roast 3 Vegetables:</strong> A sheet pan of sweet potatoes, bell peppers, and zucchini with olive oil and herbs.</li>
<li><strong>Prep 2 Proteins:</strong> Marinated grilled paneer/tofu cubes and slow-cooked seasoned black beans.</li>
<li><strong>Whisk 2 Versatile Dressings:</strong> Lemon-tahini dressing and a zesty cilantro-lime vinaigrette.</li>
</ul>',
                'product_ids' => $pickProducts(1),
                'tags' => ['meal-prep', 'productivity', 'healthy-lifestyle', 'budget-friendly', 'kitchen-tips'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(25),
                'views_count' => 820,
                'image_url' => 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Beginner Meal Prep Guide: Save Time & Eat Healthy',
                    'seo_keywords' => ['meal prep for beginners', 'batch cooking', 'healthy eating tips', 'weekly meal plan'],
                    'seo_description' => 'Learn how to meal prep efficiently to save time, reduce food waste, and maintain healthy eating habits.',
                ],
            ],

            // Blog 13
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['superfoods']->id, $categories['wellness']->id],
                'title' => 'Top 7 Superfoods to Boost Digestion and Heal Gut Microbiome',
                'slug' => 'top-7-superfoods-boost-digestion-gut-health',
                'short_description' => 'Over 70% of your immune system resides in your gut. Incorporate these 7 probiotic and prebiotic powerhouses into your diet.',
                'content' => '<h2>Your Gut: The Second Brain</h2>
<p>Modern clinical research continually highlights the vital connection between the human gut microbiome, mood regulation, metabolic health, and immune defense. Supporting beneficial gut bacteria requires a consistent supply of both probiotics (live cultures) and prebiotics (fermentable plant fibers).</p>
<h3>1. Traditional Kefir & Artisanal Yogurt</h3>
<p>Packed with diverse strains of Lactobacillus and Bifidobacterium that survive stomach acid to colonize the intestinal wall.</p>
<h3>2. Fermented Kimchi & Sauerkraut</h3>
<p>Naturally fermented cabbage delivers bioavailable organic acids and enzymes.</p>
<h3>3. Chia & Flax Seeds</h3>
<p>Soluble mucilage fiber in seeds forms a soothing gel that lubricates the gastrointestinal lining and promotes smooth motility.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['gut-health', 'superfoods', 'digestion', 'probiotics', 'microbiome'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(28),
                'views_count' => 1180,
                'image_url' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => '7 Best Superfoods for Gut Health & Digestion | Nutrition Guide',
                    'seo_keywords' => ['gut health', 'superfoods for digestion', 'probiotic foods', 'microbiome health'],
                    'seo_description' => 'Discover the top 7 superfoods that strengthen gut health, improve digestion, and boost immunity.',
                ],
            ],

            // Blog 14
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['recipes']->id, $categories['kitchen_hacks']->id],
                'title' => 'The Secret to Baking Crusty Artisanal Sourdough Bread at Home',
                'slug' => 'secret-baking-crusty-artisanal-sourdough-bread',
                'short_description' => 'Master the slow fermentation timeline, high hydration shaping, and Dutch oven baking technique for blistered, golden crusts.',
                'content' => '<h2>Flour, Water, Salt, and Patience</h2>
<p>True sourdough requires only three humble ingredients, yet unlocking an airy open crumb and crackling blistered crust is a deeply rewarding culinary journey. The secret lies in long, cool fermentation and steam management.</p>
<h3>Why Sourdough Is Easier on the Stomach</h3>
<p>During the 24-to-36-hour wild yeast fermentation, lactobacilli pre-digest complex wheat starches and break down gluten proteins, creating a low-glycemic loaf that is gentle on sensitive digestive systems.</p>
<h3>The Dutch Oven Steam Secret</h3>
<p>Baking inside a preheated heavy cast-iron Dutch oven with the lid on traps natural steam evaporating from the dough, allowing the bread to expand fully before the golden caramel crust sets.</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['baking', 'sourdough', 'artisan-bread', 'fermentation', 'home-cooking'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(30),
                'views_count' => 670,
                'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Artisanal Sourdough Bread Baking Guide | Step by Step Recipe',
                    'seo_keywords' => ['sourdough bread recipe', 'bake artisan bread', 'dutch oven bread', 'sourdough starter'],
                    'seo_description' => 'Learn how to bake bakery-quality crusty sourdough bread at home with our beginner-friendly guide.',
                ],
            ],

            // Blog 15
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['kitchen_hacks']->id, $categories['organic']->id],
                'title' => 'How to Pick a Perfectly Ripe Avocado and Prevent It from Browning',
                'slug' => 'how-to-pick-ripe-avocado-prevent-browning',
                'short_description' => 'Master the gentle stem-cap test to identify creamy ripeness, plus the airtight water storage trick to keep sliced halves emerald green.',
                'content' => '<h2>Never Open a Brown, Stringy Avocado Again</h2>
<p>Few grocery experiences are as disappointing as cutting open an avocado only to find black bruises and overripe fibers. Fortunately, identifying perfect ripeness is easy once you know what to check.</p>
<h3>The Stem-Cap Test</h3>
<p>Gently flick off the tiny brown stem at the top of the fruit. If the cavity underneath is bright vibrant green, the avocado is at peak ripeness! If it is brown, it is overripe; if it resists removal, it needs another 2 days on the countertop.</p>
<h3>How to Store Cut Avocado</h3>
<p>Brush the exposed green flesh with cold-pressed olive oil or lemon juice, place a slice of red onion alongside in an airtight container. Onion vapors contain sulfur compounds that slow down polyphenol oxidase oxidation!</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['avocado', 'kitchen-hacks', 'freshness', 'produce-guide', 'smart-cooking'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->subDays(33),
                'views_count' => 910,
                'image_url' => 'https://images.unsplash.com/photo-1523049673857-eb18f1d7b578?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'How to Tell If Avocado Is Ripe & Keep It Fresh | Kitchen Hack',
                    'seo_keywords' => ['ripe avocado', 'avocado storage', 'stop avocado browning', 'kitchen hacks'],
                    'seo_description' => 'Master the easy tricks to pick perfectly creamy ripe avocados and keep cut halves green for days.',
                ],
            ],

            // Blog 16
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['seasonal']->id, $categories['recipes']->id],
                'title' => 'Monsoon Immunity Soups: Warm, Healing Bowls for Rainy Days',
                'slug' => 'monsoon-immunity-soups-warm-healing-bowls',
                'short_description' => 'When rainy weather rolls in, these comforting, spice-infused broths with black pepper, garlic, and root vegetables keep seasonal bugs at bay.',
                'content' => '<h2>Comfort in a Bowl During Rainy Seasons</h2>
<p>The monsoon season brings cozy weather, but also increased dampness and seasonal colds. Sipping on warm herbal broths rich in garlic, shallots, crushed peppercorns, and root vegetables keeps your internal fire (Agni) strong and immune defenses sharp.</p>
<h3>1. Roasted Tomato & Basil Pepper Broth</h3>
<p>Roast vine-ripened tomatoes and garlic cloves until charred. Puree with vegetable stock, freshly torn basil, and coarse black pepper for an antioxidant-packed bowl.</p>
<h3>2. Spiced Lentil & Ginger Rasam Bowl</h3>
<p>A classic Southern broth made with tamarind, fresh curry leaves, crushed cumin, and toor dal—revered for clearing congestion and stimulating digestion.</p>',
                'product_ids' => $pickProducts(2),
                'tags' => ['monsoon', 'soups', 'immunity', 'comfort-food', 'healing-recipes'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => true,
                'published_at' => now()->subDays(35),
                'views_count' => 1620,
                'image_url' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Immunity Boosting Soup Recipes for Rainy Monsoon Season',
                    'seo_keywords' => ['immunity soup', 'monsoon recipes', 'ginger garlic soup', 'healing broth'],
                    'seo_description' => 'Warm up and boost your immune system with these flavorful herbal and spiced soup recipes.',
                ],
            ],

            // Blog 17 (Draft Example for edge testing)
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['kitchen_hacks']->id, $categories['organic']->id],
                'title' => 'Zero-Waste Kitchen: 12 Genius Ways to Repurpose Vegetable Scraps',
                'slug' => 'zero-waste-kitchen-repurpose-vegetable-scraps',
                'short_description' => 'Turn onion skins into rich vegetable stock, herb stems into vibrant chimichurri, and citrus peels into eco-friendly natural cleaners.',
                'content' => '<h2>Closing the Loop in Your Home Kitchen</h2>
<p>Nearly 30% of edible kitchen scraps end up in landfills. With a few creative culinary techniques, vegetable trimmings, cheese rinds, and citrus peels become flavor-packed gourmet ingredients.</p>
<h3>1. The Golden Freezer Scrap Bag</h3>
<p>Keep a reusable silicone bag in your freezer. Toss in carrot tops, celery ends, mushroom stems, and onion skins. When the bag is full, simmer with bay leaves and water for 1 hour to create rich, homemade vegetable stock.</p>',
                'product_ids' => $pickProducts(1),
                'tags' => ['zero-waste', 'sustainability', 'eco-living', 'kitchen-hacks'],
                'status' => BlogStatusEnum::DRAFT,
                'is_editors_pick' => false,
                'published_at' => null,
                'views_count' => 0,
                'image_url' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Zero-Waste Kitchen Guide: 12 Ways to Use Food Scraps',
                    'seo_keywords' => ['zero waste kitchen', 'repurpose food scraps', 'homemade vegetable stock'],
                    'seo_description' => 'Learn how to transform everyday vegetable scraps into rich stocks, seasonings, and zero-waste meals.',
                ],
            ],

            // Blog 18 (Future Scheduled Example for edge testing)
            [
                'author_profile_id' => $adminProfile->id,
                'category_ids' => [$categories['wellness']->id, $categories['superfoods']->id],
                'title' => 'Healthy Snack Alternatives for Late-Night Cravings',
                'slug' => 'healthy-snack-alternatives-late-night-cravings',
                'short_description' => 'Crush evening hunger without causing insulin spikes or disrupting restorative sleep with these 8 dietitian-approved snacks.',
                'content' => '<h2>Smart Snacking for Better Sleep Quality</h2>
<p>Late-night snacking on sugary or refined carbohydrate foods spikes blood glucose and suppresses natural melatonin production. Opt for snacks rich in magnesium, tryptophan, and complex fibers to stay satisfied and sleep deeply.</p>
<h3>Top Midnight Munchies</h3>
<ul>
<li><strong>Roasted Makhana (Foxnuts):</strong> Crunchy, low-calorie, and loaded with calming magnesium.</li>
<li><strong>Almond Butter on Apple Slices:</strong> The combination of fiber and healthy fats stabilizes overnight blood sugar.</li>
<li><strong>Warm Spiced Chamomile Almond Milk:</strong> Naturally soothing to the nervous system before bedtime.</li>
</ul>',
                'product_ids' => $pickProducts(1),
                'tags' => ['snacks', 'low-calorie', 'healthy-habits', 'sleep-health', 'wellness'],
                'status' => BlogStatusEnum::PUBLISHED,
                'is_editors_pick' => false,
                'published_at' => now()->addDays(3),
                'views_count' => 0,
                'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1200&auto=format&fit=crop&q=80',
                'metadata' => [
                    'seo_title' => 'Healthy Late-Night Snacks That Won’t Ruin Your Sleep',
                    'seo_keywords' => ['healthy late night snacks', 'low calorie snacks', 'sleep friendly food'],
                    'seo_description' => 'Explore 8 delicious, healthy midnight snack alternatives that satisfy hunger and promote sound sleep.',
                ],
            ],
        ];
    }
}
