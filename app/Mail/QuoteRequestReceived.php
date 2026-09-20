<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yeni teklif talebi: '.$this->quoteRequest->name.' ('.$this->quoteRequest->location_label.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request',
            with: [
                'quote' => $this->quoteRequest,
                'adminUrl' => url('/admin/quote-requests/'.$this->quoteRequest->getKey()),
            ],
        );
    }
}
