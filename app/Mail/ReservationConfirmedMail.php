<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $restaurant
     */
    public function __construct(
        public Reservation $reservation,
        public array $restaurant,
        public string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your reservation is confirmed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.confirmed',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
