<?php

use App\Contracts\AiDriverInterface;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\BlogCategory;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\AnthropicDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyLicense::class,
        CheckInstallation::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
    Gate::before(fn () => true);

    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->actingAs($this->admin, GuardNameEnum::ADMIN->value);
});

test('admin can generate blog content via ai endpoint', function () {
    $category = BlogCategory::factory()->create(['name' => 'Organic Living']);

    $mockAiManager = Mockery::mock(AiManager::class);
    $mockAiManager->shouldReceive('generateBlogContent')
        ->once()
        ->with(Mockery::on(function ($params) use ($category) {
            return $params['topic'] === '10 Benefits of Avocados'
                && $params['category_name'] === 'Organic Living'
                && $params['tone'] === 'engaging'
                && $params['length'] === 'standard';
        }))
        ->andReturn([
            'title' => '10 Incredible Health Benefits of Organic Avocados',
            'short_description' => 'Discover why organic avocados are a superfood powerhouse and how to add them to your daily diet.',
            'content' => '<h2>Introduction</h2><p>Avocados are rich in healthy fats and essential nutrients.</p><h2>Top Benefits</h2><ul><li>High in Potassium</li><li>Heart Healthy Fats</li></ul><h2>Conclusion</h2><p>Start eating avocados today!</p>',
            'tags' => ['avocado', 'organic', 'health', 'superfood'],
            'seo_title' => '10 Health Benefits of Organic Avocados | Hyperlocal',
            'seo_keywords' => ['avocado', 'organic', 'health', 'nutrition'],
            'seo_description' => 'Learn the top 10 science-backed health benefits of eating organic avocados daily.',
        ]);

    $this->app->instance(AiManager::class, $mockAiManager);

    $response = $this->postJson(route('admin.blogs.generate-ai-content'), [
        'topic' => '10 Benefits of Avocados',
        'category_id' => $category->id,
        'tone' => 'engaging',
        'length' => 'standard',
        'keywords' => 'potassium, heart health',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', '10 Incredible Health Benefits of Organic Avocados')
        ->assertJsonPath('data.tags', ['avocado', 'organic', 'health', 'superfood'])
        ->assertJsonPath('data.seo_title', '10 Health Benefits of Organic Avocados | Hyperlocal');
});

test('blog ai generation endpoint validates required topic', function () {
    $response = $this->postJson(route('admin.blogs.generate-ai-content'), [
        'category_id' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['topic']);
});

test('gemini driver correctly generates and parses blog response', function () {
    $sampleResponseJson = json_encode([
        'title' => 'The Complete Guide to Sourdough Bread',
        'short_description' => 'Master the art of baking artisanal sourdough bread at home with this beginner-friendly guide.',
        'content' => '<h2>Getting Started</h2><p>Sourdough begins with a healthy wild yeast starter.</p>',
        'tags' => ['sourdough', 'baking', 'bread'],
        'seo_title' => 'Artisanal Sourdough Guide | Baking Tips',
        'seo_keywords' => ['sourdough', 'baking', 'recipe'],
        'seo_description' => 'Learn step-by-step how to bake perfect artisan sourdough bread at home.',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => "```json\n" . $sampleResponseJson . "\n```"],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $driver = new GeminiDriver(apiKey: 'test-gemini-key', model: 'gemini-3.8-flash');
    $result = $driver->generateBlogContent([
        'topic' => 'Sourdough Bread Guide',
        'tone' => 'informative',
    ]);

    expect($result['title'])->toBe('The Complete Guide to Sourdough Bread')
        ->and($result['tags'])->toEqual(['sourdough', 'baking', 'bread'])
        ->and($result['content'])->toContain('<h2>Getting Started</h2>')
        ->and($result['seo_title'])->toBe('Artisanal Sourdough Guide | Baking Tips');
});

test('openai driver correctly generates and parses blog response', function () {
    $sampleResponseJson = json_encode([
        'title' => 'Top 5 Morning Smoothies for Instant Energy',
        'short_description' => 'Boost your morning with these nutritious, quick-to-make smoothie recipes.',
        'content' => '<h2>Energy Boosters</h2><p>Combine spinach, banana, and chia seeds for steady vitality.</p>',
        'tags' => ['smoothies', 'breakfast', 'energy'],
        'seo_title' => '5 Morning Smoothie Recipes | Quick Breakfast',
        'seo_keywords' => ['smoothies', 'energy', 'breakfast'],
        'seo_description' => 'Kickstart your day with 5 nutrient-dense morning smoothie recipes.',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => $sampleResponseJson,
                    ],
                ],
            ],
        ], 200),
    ]);

    $driver = new OpenAiDriver(apiKey: 'test-openai-key', model: 'gpt-4o-mini');
    $result = $driver->generateBlogContent([
        'topic' => 'Morning Smoothies',
        'tone' => 'engaging',
    ]);

    expect($result['title'])->toBe('Top 5 Morning Smoothies for Instant Energy')
        ->and($result['tags'])->toEqual(['smoothies', 'breakfast', 'energy'])
        ->and($result['seo_description'])->toContain('Kickstart your day');
});

test('anthropic driver correctly generates and parses blog response', function () {
    $sampleResponseJson = json_encode([
        'title' => 'Why Cold Pressed Olive Oil Matters',
        'short_description' => 'Explore the health benefits and culinary value of authentic extra virgin olive oil.',
        'content' => '<h2>Cold Pressed Purity</h2><p>Cold extraction preserves vital polyphenols.</p>',
        'tags' => ['olive-oil', 'organic', 'cooking'],
        'seo_title' => 'Cold Pressed Olive Oil Guide',
        'seo_keywords' => ['olive oil', 'cooking tips'],
        'seo_description' => 'Discover why cold pressed olive oil is essential for healthy Mediterranean cooking.',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'text' => $sampleResponseJson,
                ],
            ],
        ], 200),
    ]);

    $driver = new AnthropicDriver(apiKey: 'test-claude-key', model: 'claude-3-5-haiku-latest');
    $result = $driver->generateBlogContent([
        'topic' => 'Cold Pressed Olive Oil',
        'tone' => 'professional',
    ]);

    expect($result['title'])->toBe('Why Cold Pressed Olive Oil Matters')
        ->and($result['tags'])->toEqual(['olive-oil', 'organic', 'cooking'])
        ->and($result['seo_keywords'])->toEqual(['olive oil', 'cooking tips']);
});
