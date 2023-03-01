<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $rem_token;
    public $user;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token, $user)
    {
        $this->rem_token = $token;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Password Reset')
            ->markdown('emails.resetLink', [
                'rem_token' => $this->rem_token,
                'user' => $this->user,
                'url' => env('APP_URL') . '/pages/reset-password-cover?token=' . $this->rem_token
            ]);
    }
}
