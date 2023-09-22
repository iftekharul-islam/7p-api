<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Monogram\AppMailer;

class EmailTemplateController extends Controller
{
    public $order = null;
    public function getMessage($order, $template, $param_1 = null, $param_2 = null)
    {
        $this->order = $order;
        $subject = $this->stringParser($template->message_title);

        if ($template->type == 'view') {

            $message_body = View::make($template->message, ['order' => $order, 'store' => $order->store])->render();
        } else if ($template->type == 'message') {

            $css = "<style>body,td,th { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 0px; } " .
                "h2 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 18px; font-weight:bold; }</style>";

            $message_body = $css . $this->stringParser(str_replace('**1**', $param_1, str_replace('**2**', $param_2, $template->message)));
        }

        return ['subject' => $subject, 'message' => $message_body, 'message_type' => $template->message_type];
    }

    private function stringParser($string)
    {
        $pattern = implode("|", array_keys(config('keyword.EMAIL_TEMPLATE_KEYWORDS')));
        // escape the string
        $pattern = str_replace(array_keys(config('keyword.REGEX_ESCAPES')), array_values(array_keys(config('keyword.REGEX_ESCAPES'))), $pattern);
        $pattern = sprintf("~%s~", $pattern);
        $parsed = preg_replace_callback($pattern, [
            $this,
            "processor",
        ], $string);

        return $parsed;
    }

    private function processor($match)
    {
        $found = $match[0];
        if (array_key_exists($found, config('keyword.EMAIL_TEMPLATE_KEYWORDS'))) {
            // get the value at index 1 on email template
            $relations = config('keyword.EMAIL_TEMPLATE_KEYWORDS')[$found][1];
            // if relation as string
            if (is_string($relations)) {
                return $this->extractRelationInformation($relations);
                // given as array,
                // multiple relation
            } elseif (is_array($relations)) {
                $extracted_relation = [];
                foreach ($relations as $relation) {
                    $extracted_relation[] = $this->extractRelationInformation($relation);
                }
                // before joining the array,
                // filter down the empty results
                #return implode(", ", array_filter($extracted_relation));
                return implode(", ", $extracted_relation);
            }

            return $relations;
        }

        return $found;
    }

    private function extractRelationInformation($relation)
    {
        // explode the string based on dot
        $parts = explode(".", $relation);
        $data = $this->order;

        foreach (array_slice($parts, 1) as $part) {
            // keep extracting the data until the final data is found
            $data = $data->$part;
        }

        return $data;
    }

    public function sendMessage($from, $from_name, $to, $subject, $email_body)
    {
        //TODO - add mailgun
        return true;

        $httpClient = new \GuzzleHttp\Client();
        $response = null;
        $url = 'https://api.mailgun.net/v3/' . env('MAILGUN_DOMAIN') . '/massages';
        $mailGunApi = ['api' => env('MAILGUN_SECRET')];

        try {
            Mail::send(
                'emails.all_email_placeholder',
                compact('email_body'),
                function ($m) use ($from, $from_name, $to, $subject) {
                    $m->from($from, $from_name);
                    $m->to($to)
                        ->subject($subject)
                        ->setContentType('text/html');
                }
            );
        } catch (\Exception $e) {
            Log::error('AppMailer sendMessage: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $templates = EmailTemplate::query();
        if ($request->q) {
            $templates->where('message_type', 'like', '%' . $request->q . '%')->orWhere('message_title', 'like', '%' . $request->q . '%');
        }
        return $templates->where('is_deleted', '0')->where('type', 'message')->paginate($request->get('perPage', 10));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message_type' => 'required',
            'message_title' => 'required',
        ]);

        $data = $request->only([
            'message_type',
            'message_title',
            'message',
        ]);
        try {
            EmailTemplate::create($data);
            return response()->json([
                'message' => 'Email Template created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return EmailTemplate::find($id);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'message_type' => 'required',
            'message_title' => 'required',
        ]);
        try {
            $template = EmailTemplate::find($id);
            if (!$template) {
                return response()->json([
                    'message' => 'Email template not found.',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $data = $request->only([
                'message_type',
                'message_title',
                'message',
            ]);
            $template->update($data);
            return response()->json([
                'message' => 'Email Template updated successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string  $id)
    {
        $email_template = EmailTemplate::find($id);
        if (!$email_template) {
            return response()->json([
                'message' => 'Email template not found.',
                'status' => 203,
                'data' => []
            ], 203);
        }
        $email_template->is_deleted = '1';
        $email_template->save();

        return response()->json([
            'message' => 'Email template is successfully deleted.',
            'status' => 201,
            'data' => []
        ], 201);
    }

    public function emailTemplateKeywords()
    {
        $keywords = [];
        foreach (config('keyword.EMAIL_TEMPLATE_KEYWORDS') ?? [] as $key => $value) {
            $keywords[] = $key . '>>' . $value[0];
        };
        return $keywords;
    }

    public function emailTemplateOptions()
    {
        $email_template = EmailTemplate::where('is_deleted', '0')->get();
        $email_template->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['message_title']
            ];
        });
        return $email_template;
    }

    public function sendBulkEmail(Request $request)
    {
        if (!$request->get('template'))
            return response()->json([
                'message' => 'Mail sent successfully.',
                'error' => ['Template is required.'],
                'success' => null,
                'status' => 422,
                'data' => []
            ], 422);

        $order_ids = array_filter(explode(",", trim(preg_replace('/\s+/', ',', $request->get('order_ids')))));

        if ($request->get('id_type') != '5p') {
            $order_ids_in_database = Order::where('is_deleted', '0')->whereIn('short_order', $order_ids)->get();
        } else {
            $order_ids_in_database = Order::where('is_deleted', '0')->whereIn('id', $order_ids)->get();
        }

        $diff = [];
        if (count($order_ids) != count($order_ids_in_database)) {
            if ($request->get('id_type') != '5p') $order_ids_in_database1 = $order_ids_in_database->pluck('short_order')->all();
            else $order_ids_in_database1 = $order_ids_in_database->pluck('id')->all();
            $diff = array_diff($order_ids, $order_ids_in_database1);
        }

        $success = array();
        $error = array();

        foreach ($order_ids_in_database as $order) {
            set_time_limit(0);

            if ($request->has('param_1')) {
                $param_1 = $request->get('param_1');
            } else {
                $param_1 = null;
            }

            if ($request->has('param_2')) {
                $param_2 = $request->get('param_2');
            } else {
                $param_2 = null;
            }

            $msg = $this->getMessage($order, EmailTemplate::find($request->get('template')), $param_1, $param_2);

            if ($this->sendMessage($order->store->email, $order->store->store_name, $order->customer->bill_email, $msg['subject'], $msg['message'])) {
                Order::note("E-mail Sent " . $msg['message_type'], $order->id, $order->order_id);

                $record = new Notification;
                $record->type = $msg['message_type'];
                $record->order_5p = $order->id;
                $record->created_at = Carbon::now();
                $record->save();

                $success[] = "E-mail Sent for " . $order->id . ' -> ' . $order->short_order;
            } else {
                Order::note("Message Failed to Send " . $msg['message_type'], $order->id, $order->order_id);

                $error[] = "E-mail Failed for " . $order->id . ' -> ' . $order->short_order;
            }
        }
        foreach ($diff as $order) {
            $error[] = $order . " is invalid";
        }

        return response()->json([
            'message' => 'Mail sent successfully.',
            'error' => $error,
            'success' => $success,
            'status' => 201,
            'data' => []
        ], 201);
    }
}
