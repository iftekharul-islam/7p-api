<?php
return [

    "REGEX_ESCAPES" => [
        '.' => '\.',
    ],

    "EMAIL_TEMPLATE_KEYWORDS" => [
        /*'TEMPLATE-KEY'           => [
			'replaceable-value-on-view',
			'replaceable-value-on-code-relationship-or-closure',
		],*/
        '@@STORENAME@@'          => [
            'store name',
            'order.store.store_name',
        ],
        '@@NAME@@'               => [
            'customer name',
            'order.customer.ship_full_name',
        ],
        '@@B_NAME@@'             => [
            'billed customer name',
            'order.customer.bill_full_name',
        ],
        '@@FIRST@@'              => [
            'customer first name',
            'order.customer.ship_first_name',
        ],
        '@@LAST@@'               => [
            'customer last name',
            'order.customer.ship_last_name',
        ],
        '@@ID@@'                 => [
            'order Id',
            'order.order_id',
        ],
        '@@IDS@@'                => [
            'short order Id',
            'order.short_order',
        ],
        /*'@@4PID@@'               => [
			'4P order #',
			'customer.full_name',
		],*/
        '@@ODATE@@'              => [
            'Order date',
            'order.order_date',
        ],
        '@@COMPANY@@'            => [
            'company name',
            'order.store.store_name',
        ],
        /*'@@SIGN@@'               => [
			'Contact name',
			'customer.full_name',
		],*/
        '@@URL@@'                => [
            'Company main domain',
            'order.store_name',
        ],
        /*'@@EMAIL@@'              => [
			'Customer support email',
			'-------',
		],*/
        /*'@@PHONE@@'              => [
			'company phone',
			'-------',
		],*/
        /*'@@RMA@@'                => [
			'Order RMA',
			'-------',
		],*/
        '@@ShipTo.FullAddress@@' => [
            'Full shipping address',
            [
                'order.customer.ship_address_1',
                'order.customer.ship_address_2',
                'order.customer.ship_city',
                'order.customer.ship_state',
                'order.customer.ship_zip',
                'order.customer.ship_country',
                'order.customer.ship_phone',
            ],
        ],
        '@@BillTo.FullAddress@@' => [
            'Full billing address',
            [
                'order.customer.bill_address_1',
                'order.customer.bill_address_2',
                'order.customer.bill_city',
                'order.customer.bill_state',
                'order.customer.bill_zip',
                'order.customer.bill_country',
                'order.customer.bill_phone',
            ],
        ],
        '@@Lines.Summary@@'      => [
            'order lines & summary',
            '-------',
        ],
        '@@Lines.Only@@'         => [
            'order lines',
            '-------',
        ],
        '@@Lines.Only.BO@@'      => [
            'order lines that are on b/o',
            '-------',
        ],
        '@@Lines.Only.NP@@'      => [
            'order lines that w/o price',
            '-------',
        ],
        '@@USERNAME@@'           => [
            'User\'s name',
            '-------',
        ],
        '@@DATE@@'               => [
            'Email date',
            '-------',
        ],
        '@@SHIPMETHOD@@'         => [
            'Order ship method',
            'order.customer.shipping',
        ],
        '@@CC@@'                 => [
            'Credit Card #',
            '-------',
        ],
        '@@EXPIRE@@'             => [
            'CC expiration date',
            '-------',
        ],
        '@@RETVAL@@'             => [
            'Return total',
            '-------',
        ],
        '@@COMPADDR@@'           => [
            'Company address',
            '-------',
        ],
        '@@TRK@@'                => [
            'order trk#',
            '-------',
        ],
        '@@ORDERTOTAL@@'         => [
            'order total',
            '-------',
        ],
        '@@GIFTWRAPMESSAGE@@'    => [
            'Gift message',
            '-------',
        ],
        '@@SHIPPHONE@@'          => [
            'Ship to phone',
            '-------',
        ],
        '@@LOGO@@'               => [
            'store/company logo',
            '-------',
        ],
        '@@COMM@@'               => [
            'customer comments',
            '-------',
        ],
        '@@CEMAIL@@'             => [
            'customer\'s email',
            '-------',
        ],
        '@@ITEM@@'               => [
            'Product SKU/Name',
            '-------',
        ],
        '@@ITEMCODE@@'           => [
            'Product SKU/Code',
            '-------',
        ],
        '@@ITEMNAME@@'           => [
            'Item name',
            '-------',
        ],
        /*'---'                    => [
			'-------',
			'-------',
		],*/
    ]
];
