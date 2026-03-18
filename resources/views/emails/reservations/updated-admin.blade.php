<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Reservation updated</title>
    </head>
    <body style="margin:0;background:#f5f5f4;color:#1f2937;font-family:Arial,sans-serif;">
        <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
            <div style="background:#fff;border-radius:24px;padding:32px;border:1px solid #e7e5e4;">
                <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#57534e;">
                    Admin notification
                </p>
                <h1 style="margin:0 0 12px;font-size:30px;line-height:1.1;color:#1c1917;">
                    Reservation updated at {{ $restaurant['name'] }}
                </h1>
                <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#57534e;">
                    A guest changed their booking through the public management link.
                </p>

                <div style="border-radius:18px;background:#fafaf9;padding:20px 22px;margin-bottom:20px;">
                    <p style="margin:0 0 8px;font-size:14px;color:#78716c;">Guest</p>
                    <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#1c1917;">
                        {{ $reservation->customer_name }} · {{ $reservation->customer_email }}
                    </p>
                    <p style="margin:0 0 8px;font-size:14px;color:#78716c;">Updated booking</p>
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

                <div style="border-radius:18px;background:#fff7ed;padding:20px 22px;margin-bottom:24px;border:1px solid #fed7aa;">
                    <p style="margin:0 0 8px;font-size:14px;color:#9a3412;">Previous booking</p>
                    <p style="margin:0 0 4px;font-size:16px;font-weight:700;color:#7c2d12;">
                        {{ $previousReservation['date'] }} at {{ $previousReservation['time'] }}
                    </p>
                    <p style="margin:0 0 4px;font-size:16px;color:#9a3412;">
                        Table: {{ $previousReservation['table_name'] }}
                    </p>
                    <p style="margin:0;font-size:16px;color:#9a3412;">
                        Party size: {{ $previousReservation['people_count'] }}
                    </p>
                </div>

                <a
                    href="{{ $manageUrl }}"
                    style="display:inline-block;padding:14px 22px;border-radius:999px;background:#111827;color:#fff;text-decoration:none;font-weight:700;"
                >
                    Open public management link
                </a>
            </div>
        </div>
    </body>
</html>
