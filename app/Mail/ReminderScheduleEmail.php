<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class ReminderScheduleEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $messages;
    /**
     * Create a new message instance.
     */
    public function __construct($messages, $name)
    {
        $this->messages = $messages;
        $this->name = $name;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // return new Envelope(
        //     subject: 'Reminder Schedule Email',
        // );
        return new Envelope(
            subject: "Reminder Goals Setting",
            replyTo: [new Address("alfian.azis@kpn-corp.com", "Alfian")],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // return new Content(
        //     view: 'view.name',
        // );
        return new Content(
            view: "email.reminderschedule", // forgot.blade.php yg ada di folder email
            with: [
                "messages" => $this->messages,
                "name" => $this->name,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
