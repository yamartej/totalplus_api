<?php

namespace App\Notifications;

use GuzzleHttp\Psr7\Message;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends VerifyEmail
{
    /**
     * Get the notification's mail message.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->view('notifications.verify-email', [
                'url' => $verificationUrl,
                'user' => $notifiable,
            ]);
    }
}
