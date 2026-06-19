<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun payment.naeva.id berhasil dibuat')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Akun Anda untuk dashboard utama payment.naeva.id sudah aktif.')
            ->line('Anda sekarang bisa login untuk mengelola project, transaksi, dan monitoring payment service.')
            ->action('Buka Dashboard', url(route('dashboard', absolute: false)))
            ->line('Jika pendaftaran ini bukan dari Anda, segera ganti password dan hubungi tim internal Naeva.');
    }
}
