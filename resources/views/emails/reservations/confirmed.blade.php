<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Reservation confirmed</title>
    </head>
    <body style="margin:0;background:#f4efe8;color:#1f2937;font-family:Arial,sans-serif;">
        <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
            <div style="background:#fff;border-radius:24px;padding:32px;border:1px solid #eadfd2;">
                <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#9a6c3b;">
                    Reservation confirmed
                </p>
                <h1 style="margin:0 0 12px;font-size:32px;line-height:1.1;color:#1c1917;">
                    {{ $restaurant['name'] }}
                </h1>
                <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#57534e;">
                    Your table is booked. Keep this email handy if you need to review or cancel your reservation.
                </p>

                <div style="border-radius:18px;background:#faf7f2;padding:20px 22px;margin-bottom:24px;">
                    <p style="margin:0 0 8px;font-size:14px;color:#78716c;">
                        Guest
                    </p>
                    <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#1c1917;">
                        {{ $reservation->customer_name }} · {{ $reservation->customer_email }}
                    </p>

                    <p style="margin:0 0 8px;font-size:14px;color:#78716c;">
                        Reservation
                    </p>
                    <p style="margin:0 0 4px;font-size:16px;font-weight:700;color:#1c1917;">
                        {{ $reservation->date?->format('F j, Y') }} at {{ substr((string) $reservation->time, 0, 5) }}
                    </p>
                    <p style="margin:0 0 4px;font-size:16px;color:#44403c;">
                        Table: {{ $reservation->restaurantTable?->name ?? 'Assigned table' }}
                    </p>
                    <p style="margin:0;font-size:16px;color:#44403c;">
                        Party size: {{ $reservation->people_count }}
                    </p>
                </div>

                <a
                    href="{{ $manageUrl }}"
                    style="display:inline-block;padding:14px 22px;border-radius:999px;background:#1f4d3d;color:#fff;text-decoration:none;font-weight:700;"
                >
                    Manage reservation
                </a>

                <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#78716c;">
                    If you did not request this reservation, please contact the restaurant directly.
                </p>
            </div>
        </div>
    </body>
</html>
