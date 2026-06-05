<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentPasswordResetCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Student $student,
        public string $code,
        public int $expiresInMinutes
    ) {
    }

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'a-tenda');

        return new Envelope(
            subject: $appName.' password reset code: '.$this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student-password-reset',
            with: [
                'student' => $this->student,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'appName' => (string) config('app.name', 'a-tenda'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
