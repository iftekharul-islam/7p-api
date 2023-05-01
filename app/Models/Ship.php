<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ship extends Model
{
    protected $table = 'shipping';

    private function tableColumns()
    {
        $columns = $this->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->getTable());

        return array_slice($columns, 1, -2);
    }

    public static function getTableColumns()
    {
        return (new static())->tableColumns();
    }

    public function order()
    {
        return $this->belongsTo('App\Order', 'order_number', 'id');
    }

    public function items()
    {
        return $this->hasMany('App\Item', 'tracking_number', 'tracking_number')
            ->where('is_deleted', 0)
            ->where('item_status', 2);
    }

    public function item()
    {
        return $this->hasOne('App\Item', 'tracking_number', 'tracking_number')
            ->where('is_deleted', 0)
            ->where('item_status', 2);
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function scopeSearchCriteria($query, $search_for, $search_in)
    {
        $search_for = trim($search_for);
        if (in_array($search_in, array_keys(ShippingController::$search_in))) {
            /*
			 * camel case method converts the key to camel case
			 * uc first converts the word to upper case first to match the method name
			 */
            $search_function_to_respond = sprintf("scopeSearch%s", ucfirst(camel_case($search_in)));

            return $this->$search_function_to_respond($query, $search_for);
        }

        return;
    }


    public function scopeSearchBatchNumber($query, $batch_number)
    {
        if (empty($batch_number)) {
            return;
        }

        return $query->whereHas('items', function ($q) use ($batch_number) {
            $q->where('batch_number', $batch_number);
        });
    }

    public function scopeSearchUniqueOrderId($query, $packageId)
    {
        if (empty($packageId)) {
            return;
        }

        if (strpos($packageId,  '-') == false) {
            $packageId = substr_replace($packageId, '%', -1, 0);
        }

        return $query->where('unique_order_id', "LIKE", sprintf("%%%s%%", $packageId));
    }

    public function scopeSearchAddressOne($query, $address_1)
    {
        if (empty($address_1)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($address_1) {
            return $q->whereHas('customer', function ($q2) use ($address_1) {
                $q2->where('ship_address_1', 'LIKE', sprintf("%%%s%%", $address_1));
            });
        });
    }

    public function scopeSearchUser($query, $username)
    {
        if (empty($username)) {
            return;
        }

        return $query->whereHas('user', function ($q) use ($username) {
            return $q->where('username', 'LIKE', sprintf("%%%s%%", $username));
        });
    }

    public function scopeSearchName($query, $name)
    {
        if (empty($name)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($name) {
            return $q->whereHas('customer', function ($q2) use ($name) {
                $q2->where('ship_full_name', 'LIKE', sprintf("%%%s%%", $name));
            });
        });
    }

    public function scopeSearchOrderId($query, $order_id)
    {
        if (empty($order_id)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($order_id) {
            $q->where('order_id', "LIKE", sprintf("%%%s%%", $order_id));
        });
    }

    public function scopeSearchOrderNumber($query, $order_number)
    {
        if (empty($order_number)) {
            return;
        }

        return $query->where('order_number', "LIKE", sprintf("%%%s%%", $order_number));
    }

    public function scopeSearchStoreId($query, $store_id)
    {
        logger($store_id);
        $stores = Store::query();
        if (count($store_id)) {
            $stores->whereIn('store_id', $store_id);
        }
        $store_id = $stores->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->where('is_deleted', '0')
            ->where('invisible', '0')
            ->get()
            ->pluck('store_id')
            ->toArray();

        if (count($store_id)) {
            return $query->whereHas('order', function ($q) use ($store_id) {
                $q->whereIn('store_id', $store_id);
            });
        } else {
            return $query->whereHas('order', function ($q) use ($store_id) {
                $q->whereIn('store_id', "LIKE", sprintf("%%%s%%", $store_id));
            });
        }
    }

    public function scopeSearchPackageShape($query, $package_shape)
    {
        if (empty($package_shape)) {
            return;
        }

        return $query->where('package_shape', "LIKE", sprintf("%%%s%%", $package_shape));
    }

    public function scopeSearchCompany($query, $company)
    {
        if (empty($company)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($company) {
            return $q->whereHas('customer', function ($q2) use ($company) {
                $q2->where('ship_company_name', 'LIKE', sprintf("%%%s%%", $company));
            });
        });
    }

    public function scopeSearchCity($query, $city)
    {
        if (empty($city)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($city) {
            return $q->whereHas('customer', function ($q2) use ($city) {
                $q2->where('ship_city', 'LIKE', sprintf("%%%s%%", $city));
            });
        });
    }

    public function scopeSearchState($query, $state)
    {
        if (empty($state)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($state) {
            return $q->whereHas('customer', function ($q2) use ($state) {
                $q2->where('ship_state', 'LIKE', sprintf("%%%s%%", $state));
            });
        });
    }

    public function scopeSearchPostalCode($query, $postal_code)
    {
        if (empty($postal_code)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($postal_code) {
            return $q->whereHas('customer', function ($q2) use ($postal_code) {
                $q2->where('ship_zip', 'LIKE', sprintf("%%%s%%", $postal_code));
            });
        });
    }

    public function scopeSearchCountry($query, $country)
    {
        if (empty($country)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($country) {
            return $q->whereHas('customer', function ($q2) use ($country) {
                $q2->where('ship_country', 'LIKE', sprintf("%%%s%%", $country));
            });
        });
    }

    public function scopeSearchEmail($query, $email)
    {
        if (empty($email)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($email) {
            return $q->whereHas('customer', function ($q2) use ($email) {
                $q2->where('bill_email', 'LIKE', sprintf("%%%s%%", $email));
            });
        });
    }

    public function scopeSearchTransactionId($query, $transaction_id)
    {
        if (empty($transaction_id)) {
            return;
        }

        return $query->where('transaction_id', intval($transaction_id));
    }

    public function scopeSearchItemId($query, $item_id)
    {
        if (empty($item_id)) {
            return;
        }

        return $query->whereHas('order', function ($q) use ($item_id) {
            return $q->whereHas('items', function ($q2) use ($item_id) {
                $q2->where('id', $item_id);
            });
        });
    }

    public function scopeSearchTrackingNumber($query, $tracking_number)
    {
        if (empty($tracking_number)) {
            return;
        }

        return $query->where('shipping_id', "LIKE", sprintf("%%%s%%", trim($tracking_number)))
            ->orWhere('tracking_number', "LIKE", sprintf("%%%s%%", trim($tracking_number)));
    }

    public function scopeSearchMailClass($query, $mail_class)
    {
        if (empty($mail_class)) {
            return;
        }

        return $query->where('mail_class', "LIKE", sprintf("%%%s%%", $mail_class));
    }

    public function scopeSearchTrackingType($query, $tracking_type)
    {
        if (empty($tracking_type)) {
            return;
        }

        return $query->where('tracking_type', "LIKE", sprintf("%%%s%%", $tracking_type));
    }


    public function scopeSearchWithinDate($query, $start_date, $end_date)
    {
        if (!$start_date) {
            return;
        }

        if (!$end_date) {
            $end_date = date("Y-m-d");
        }
        // formatting the date again, if, malformed, won't crash
        $start_date = date('Y-m-d', strtotime($start_date));
        if ($end_date) {
            $end_date = date('Y-m-d', strtotime($end_date));
        } else {
            $end_date = $start_date;
        }
        $starting = $start_date . " 00:00:00";
        $ending = $end_date . " 23:59:59";
        // 		dd($starting, $ending);
        // postmark_date transaction_datetime
        return $query->where('transaction_datetime', '>=', $starting)
            ->where('transaction_datetime', '<=', $ending);
    }

    public function outputArray()
    {
        return [
            'App\Ship',
            $this->id,
            url(sprintf('shipping?search_for_first=%s&search_in_first=unique_order_id', $this->unique_order_id)),
            'Shipment: ' . $this->unique_order_id,
            $this->tracking_number,
            'Order: ' . $this->order->short_order
        ];
    }
}
