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

        /*return (new MailMessage)
            ->subject('Verifica tu correo electrónico Jairo Jose')
            ->line('Haz click en el siguiente enlace para verificar tu correo electrónico:')
            ->action('Verificar correo electrónico', $verificationUrl)
            ->line('Si no creaste una cuenta, puedes ignorar este correo electrónico.');*/
        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->view('notifications.verify-email', ['url' => $verificationUrl]);
    }
}
