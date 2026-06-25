<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Monta o e-mail de redefinição de senha em pt_BR.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha — SIE Matrícula')
            ->greeting('Olá'.($notifiable->name ? ', '.$notifiable->name : '').'!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta no SIE Matrícula.')
            ->action('Redefinir senha', $url)
            ->line("Este link expira em {$expire} minutos.")
            ->line('Se você não solicitou a redefinição, ignore este e-mail — nenhuma alteração será feita.')
            ->salutation('Atenciosamente, Equipe SIE Matrícula');
    }
}
