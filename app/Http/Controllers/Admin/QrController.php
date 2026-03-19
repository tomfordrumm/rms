<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $restaurant = $this->restaurantFromRequest($request);

        $menuUrl = route('restaurants.menu', ['slug' => $restaurant->slug]);
        $bookingUrl = route('restaurants.booking.show', ['slug' => $restaurant->slug]);

        return Inertia::render('admin/qr/Index', [
            'restaurant_name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'menu_url' => $menuUrl,
            'booking_url' => $bookingUrl,
            'menu_qr_svg' => $this->generateQrSvg($menuUrl),
            'booking_qr_svg' => $this->generateQrSvg($bookingUrl),
        ]);
    }

    private function restaurantFromRequest(Request $request): Restaurant
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        return $restaurant;
    }

    private function generateQrSvg(string $content): string
    {
        return (new Writer(
            new ImageRenderer(
                new RendererStyle(
                    256,
                    8,
                    null,
                    null,
                    Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39)),
                ),
                new SvgImageBackEnd(),
            ),
        ))->writeString($content);
    }
}
