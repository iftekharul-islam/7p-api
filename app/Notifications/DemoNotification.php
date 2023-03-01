<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemoNotification extends Notification
{
    use Queueable;
    public $receiver;
    public $data;
    public $authUser;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($receiver, $data, $authUser)
    {
        //demo data

        // $this->receiver = $receiver;
        // $this->data = $data;
        // $this->authUser = $authUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        //demo data
        
        // $url = url(env('APP_URL') . '/apps/kpi/window-employee-goal/' . $this->data->window_employee_id);

        // return (new MailMessage)
        //     ->greeting('Hello ' . $this->receiver->name . '!')
        //     ->line('Your KPI goal review is complete!')
        //     ->line('Please Take a look')
        //     ->action('Take a look', $url)
        //     ->line('Thank you!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user' => $this->authUser,
            'title' => "Your KPI goal review is complete!",
            'subtitle' => "Please Take a look",
            'for'     => 'window',
            'url'     => '/apps/kpi/window-employee-goal/' . $this->data->window_employee_id,
            'data' => $this->data
        ];
    }
}
