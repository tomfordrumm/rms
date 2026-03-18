<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MagicOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.model', 'gpt-4o-mini');
    }

    public function test_magic_order_returns_recommendations_for_existing_restaurant(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'Balanced, seafood-forward dinner with a clean finish.',
                                    'items' => [
                                        [
                                            'dish_id' => 1,
                                            'quantity' => 2,
                                            'reason' => 'Fits the request for a light but satisfying main.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $restaurant = Restaurant::factory()->create([
            'slug' => 'ember-table',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'id' => 1,
            'name' => 'Roasted cod',
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'I want a light dinner with seafood and no red meat.',
        ])
            ->assertOk()
            ->assertJsonPath('summary', 'Balanced, seafood-forward dinner with a clean finish.')
            ->assertJsonPath('items.0.dish_id', 1)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.name', 'Roasted cod');
    }

    public function test_magic_order_returns_not_found_for_unknown_restaurant(): void
    {
        $this->postJson('/r/missing/magic-order', [
            'preferences' => 'Something comforting.',
        ])->assertNotFound();
    }

    public function test_magic_order_validates_preferences(): void
    {
        $restaurant = Restaurant::factory()->create([
            'slug' => 'harbor-kitchen',
        ]);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => '',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('preferences');

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => str_repeat('a', 2001),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('preferences');
    }

    public function test_magic_order_sends_only_active_dishes_to_openai(): void
    {
        Http::fake(function ($request) {
            $payload = $request->data();
            $inputText = $payload['input'][1]['content'][0]['text'] ?? '';
            $decoded = json_decode($inputText, true);

            $this->assertIsArray($decoded);
            $this->assertCount(1, $decoded['menu']);
            $this->assertSame('Active dish', $decoded['menu'][0]['name']);

            return Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'Summary',
                                    'items' => [
                                        [
                                            'dish_id' => $decoded['menu'][0]['dish_id'],
                                            'quantity' => 1,
                                            'reason' => 'Reason',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]);
        });

        $restaurant = Restaurant::factory()->create([
            'slug' => 'studio-flame',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();

        $activeDish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Active dish',
            'is_active' => true,
        ]);
        $inactiveDish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Inactive dish',
            'is_active' => false,
        ]);

        $activeDish->categories()->attach($category);
        $inactiveDish->categories()->attach($category);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'Anything good.',
        ])->assertOk();
    }

    public function test_magic_order_filters_invalid_or_inactive_model_recommendations(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'Summary',
                                    'items' => [
                                        ['dish_id' => 99999, 'quantity' => 1, 'reason' => 'Unknown'],
                                        ['dish_id' => 1, 'quantity' => 0, 'reason' => 'Bad quantity'],
                                        ['dish_id' => 1, 'quantity' => 2, 'reason' => 'Valid'],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $restaurant = Restaurant::factory()->create([
            'slug' => 'narrow-room',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'id' => 1,
            'name' => 'Valid dish',
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'Pick for me.',
        ])
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.dish_id', 1)
            ->assertJsonPath('items.0.quantity', 2);
    }

    public function test_magic_order_returns_unprocessable_when_no_usable_recommendations_exist(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'Summary',
                                    'items' => [],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $restaurant = Restaurant::factory()->create([
            'slug' => 'empty-room',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'Anything.',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'We could not assemble a reliable recommendation from the current menu.');
    }

    public function test_magic_order_returns_bad_gateway_when_openai_fails(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([], 500),
        ]);

        $restaurant = Restaurant::factory()->create([
            'slug' => 'signal-room',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'Something seasonal.',
        ])->assertStatus(502)
            ->assertJsonPath('message', 'Magic order is temporarily unavailable. Please try again shortly.');
    }

    public function test_magic_order_is_throttled_after_five_requests_per_minute(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'Summary',
                                    'items' => [
                                        ['dish_id' => 1, 'quantity' => 1, 'reason' => 'Reason'],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $restaurant = Restaurant::factory()->create([
            'slug' => 'throttle-room',
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'id' => 1,
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
                'preferences' => 'Surprise me.',
            ])->assertOk();
        }

        $this->postJson(route('restaurants.magic-order', ['slug' => $restaurant->slug]), [
            'preferences' => 'Surprise me.',
        ])->assertStatus(429);
    }
}
