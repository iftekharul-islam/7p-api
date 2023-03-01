<?php

namespace App\Console\Commands;

use App\Mail\ContractRemainder\ContractRemainderMail;
use App\Models\Contract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ContractRemainder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // $user = User::with('details.company.contracts')->whereRelation('details.company.contracts')->get()->toArray();
        // info($user);

        $words = [

            '
            Dear Concern,

            Your contract will expeir in 15 days
            see that in your portal,
            please contact with your provider

            Regards
            Naf
            ',
        ];

        // Finding a random word
        $key = array_rand($words);
        $value = $words[$key];

        $contracts = Contract::with('company.users')->get();

        foreach ($contracts as $contract) {
            $diff = Carbon::now()->diffInDays(Carbon::parse($contract->end_date));
            if ($diff <= 15) {
                $users = $contract->company?->users;
                foreach ($users as $user) {
                    // Mail::to($user)->send(new ContractRemainderMail);
                    Mail::raw("{$value}", function ($mail) use ($user) {
                        $mail->from('info@viserx.com');
                        $mail->to($user->email)
                            ->subject('Contract Remainder');
                    });

                    // Mail::send('vendor.mail.html.default',$words, function($message) use($user) {
                    //     $message->to('shohorabshanto@gmail.com');
                    //     $message->subject('New email!!!');
                    // });
                }
            } else {
                info('Not in 15 days');
            }

            $this->info('This message sent to All Users');
        }

        // echo "this is my first command";
    }
}
