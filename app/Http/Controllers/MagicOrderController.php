<?php

namespace App\Http\Controllers;

use App\Http\Requests\MagicOrderRequest;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MagicOrderController extends Controller
{
    public function __invoke(MagicOrderRequest $request, string $slug): JsonResponse
    {
        $restaurant = Restaurant::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $dishes = $restaurant->dishes()
            ->where('is_active', true)
            ->with(['categories' => fn ($query) => $query
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('position')
                ->orderBy('name')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($dishes->isEmpty()) {
            return response()->json([
                'message' => 'There are no active dishes available for recommendations yet.',
            ], 422);
        }

        $rawRecommendation = $this->requestRecommendation(
            restaurant: $restaurant,
            preferences: $request->string('preferences')->toString(),
            dishes: $dishes,
        );

        $items = collect($rawRecommendation['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($dishes): ?array {
                $dishId = filter_var($item['dish_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

                if ($dishId === false || $quantity === false || $quantity < 1) {
                    return null;
                }

                /** @var Dish|null $dish */
                $dish = $dishes->firstWhere('id', $dishId);

                if ($dish === null) {
                    return null;
                }

                return [
                    'dish_id' => $dish->id,
                    'name' => $dish->name,
                    'description' => $dish->description,
                    'weight' => $dish->weight,
                    'price' => $dish->price,
                    'image_url' => $dish->image_path ? Storage::disk('public')->url($dish->image_path) : null,
                    'quantity' => $quantity,
                    'reason' => trim((string) ($item['reason'] ?? '')),
                ];
            })
            ->filter()
            ->unique('dish_id')
            ->values()
            ->all();

        if ($items === []) {
            return response()->json([
                'message' => 'We could not assemble a reliable recommendation from the current menu.',
            ], 422);
        }

        return response()->json([
            'summary' => trim((string) ($rawRecommendation['summary'] ?? '')),
            'items' => $items,
        ]);
    }

    /**
     * @param  Collection<int, Dish>  $dishes
     * @return array{summary?:mixed,items?:mixed}
     */
    private function requestRecommendation(Restaurant $restaurant, string $preferences, Collection $dishes): array
    {
        $apiKey = (string) config('services.openai.api_key');
        $model = (string) config('services.openai.model');

        if ($apiKey === '' || $model === '') {
            abort(response()->json([
                'message' => 'Magic order is temporarily unavailable. Please try again shortly.',
            ], 502));
        }

        $catalog = $dishes->map(fn (Dish $dish): array => [
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'description' => $dish->description,
            'weight' => $dish->weight,
            'price' => $dish->price,
            'categories' => $dish->categories->pluck('name')->values()->all(),
        ])->values()->all();

        try {
            $response = Http::timeout(20)
                ->retry(1, 200)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $this->systemInstruction($restaurant->name),
                                ],
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => json_encode([
                                        'preferences' => $preferences,
                                        'restaurant_name' => $restaurant->name,
                                        'menu' => $catalog,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                    'temperature' => 0.3,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'magic_order_recommendation',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'summary' => [
                                        'type' => 'string',
                                    ],
                                    'items' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'additionalProperties' => false,
                                            'properties' => [
                                                'dish_id' => ['type' => 'integer'],
                                                'quantity' => ['type' => 'integer'],
                                                'reason' => ['type' => 'string'],
                                            ],
                                            'required' => ['dish_id', 'quantity', 'reason'],
                                        ],
                                    ],
                                ],
                                'required' => ['summary', 'items'],
                            ],
                        ],
                    ],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            abort(response()->json([
                'message' => 'Magic order is temporarily unavailable. Please try again shortly.',
            ], 502));
        }

        $responsePayload = $response->json();
        $responseText = $this->extractResponseText(is_array($responsePayload) ? $responsePayload : []);
        $decoded = json_decode($responseText ?? '', true);

        if (! is_array($decoded)) {
            abort(response()->json([
                'message' => 'We could not assemble a reliable recommendation from the current menu.',
            ], 422));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractResponseText(array $payload): ?string
    {
        $outputText = $payload['output_text'] ?? null;

        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $segments = collect($payload['output'] ?? [])
            ->filter(fn (mixed $output): bool => is_array($output))
            ->flatMap(function (array $output): array {
                $content = $output['content'] ?? [];

                if (! is_array($content)) {
                    return [];
                }

                return collect($content)
                    ->filter(fn (mixed $item): bool => is_array($item) && isset($item['text']) && is_string($item['text']))
                    ->map(fn (array $item): string => $item['text'])
                    ->all();
            })
            ->filter(fn (string $text): bool => trim($text) !== '')
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        return $segments->implode("\n");
    }

    private function systemInstruction(string $restaurantName): string
    {
        return <<<PROMPT
You are building a food recommendation for {$restaurantName}.

You must recommend dishes only from the provided menu catalog.
Every recommended item must use a real dish_id from the catalog.
Do not invent dishes, categories, prices, dietary facts, or constraints.
Respect the user's preferences, dislikes, and restrictions as much as possible using only the catalog.
Return between 1 and 6 recommended items.
Each item must include:
- dish_id
- quantity as a positive integer
- a short reason for why it fits

Also return a concise summary explaining the overall selection.
Return only JSON matching the requested schema.
PROMPT;
    }
}
