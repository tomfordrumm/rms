<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationUpdatedGuestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $restaurant
     * @param  array<string, mixed>  $previousReservation
     */
    public function __construct(
        public Reservation $reservation,
        public array $restaurant,
        public array $previousReservation,
        public string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your reservation has been updated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.updated-guest',
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
