<?php

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Models\AdCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/ads/bulk-clicks', function () {
    it('validates required fields', function () {
        $response = $this->postJson('/api/ads/bulk-clicks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events']);
    });

    it('validates event structure', function () {
        $response = $this->postJson('/api/ads/bulk-clicks', [
            'events' => [
                ['visitor_key' => 'abc'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.campaign_id']);
    });

    it('records valid click events', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 1.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $response = $this->postJson('/api/ads/bulk-clicks', [
            'events' => [
                [
                    'campaign_id' => $campaign->id,
                    'visitor_key' => hash('sha256', 'test-visitor'),
                    'timestamp'   => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recorded', 1)
            ->assertJsonPath('data.skipped', 0);
    });

    it('rejects more than 100 events', function () {
        $events = array_fill(0, 101, [
            'campaign_id' => 1,
            'visitor_key' => 'abc',
            'timestamp'   => now()->toIso8601String(),
        ]);

        $response = $this->postJson('/api/ads/bulk-clicks', ['events' => $events]);
        $response->assertStatus(422);
    });
});

describe('POST /api/ads/bulk-impressions', function () {
    it('records valid impression events', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 1.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $response = $this->postJson('/api/ads/bulk-impressions', [
            'events' => [
                [
                    'campaign_id' => $campaign->id,
                    'visitor_key' => hash('sha256', 'test-visitor'),
                    'timestamp'   => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recorded', 1);

        // Budget should not be deducted
        $campaign->refresh();
        expect((float) $campaign->spent)->toBe(0.00);
    });
});
