<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Support\MenuTemplates\RestaurantMenuTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRestaurantMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dishes:generate
        {restaurant : Restaurant ID or slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a realistic menu with categories and dishes for a restaurant';

    public function handle(): int
    {
        $restaurant = $this->resolveRestaurant((string) $this->argument('restaurant'));

        if ($restaurant === null) {
            $this->error('Restaurant not found.');

            return self::FAILURE;
        }

        [$categoriesCount, $dishesCount] = DB::transaction(function () use ($restaurant): array {
            $categoriesCount = 0;
            $dishesCount = 0;

            foreach (RestaurantMenuTemplate::categories() as $index => $categoryData) {
                $category = Category::query()->updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'name' => $categoryData['name'],
                    ],
                    [
                        'description' => $categoryData['description'],
                        'position' => $index + 1,
                    ],
                );

                $categoriesCount++;

                foreach ($categoryData['dishes'] as $dishData) {
                    $dish = Dish::query()->updateOrCreate(
                        [
                            'restaurant_id' => $restaurant->id,
                            'name' => $dishData['name'],
                        ],
                        [
                            'description' => $dishData['description'],
                            'weight' => $dishData['weight'],
                            'price' => $dishData['price'],
                            'image_path' => null,
                            'is_active' => true,
                        ],
                    );

                    $category->dishes()->syncWithoutDetaching([$dish->id]);
                    $dishesCount++;
                }
            }

            return [$categoriesCount, $dishesCount];
        });

        $this->info(sprintf(
            'Generated %d categories and %d dishes for restaurant "%s" (ID %d).',
            $categoriesCount,
            $dishesCount,
            $restaurant->name,
            $restaurant->id,
        ));

        return self::SUCCESS;
    }

    private function resolveRestaurant(string $identifier): ?Restaurant
    {
        return Restaurant::query()
            ->whereKey($identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }
}
