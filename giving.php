<?php

//PCO Personal Access Token info
$AppID = ''; // set from https://api.planningcenteronline.com/oauth/applications
$secret = '';

$create_batch_endpoint = 'https://api.planningcenteronline.com/giving/v2/batches';

echo '<pre>';
$gifts = array_map('str_getcsv', file('donations.csv'));
unset($gifts[0]);
$people = array_map('str_getcsv', file('people.csv'));
unset($people[0]);

const G_PAYMENT_SOURCE_ID = '1234'; // set to a new payment source id you create
const P_TAB_FIELD_ID = '1234'; // set to the ID of the field you import prev ID's to in PCO Tab
const P_IND_ID = 0;
const P_PCO_ID = 0;
const G_TRANS_ID = 0;
const G_PERSON_ID = 13;
const G_FUND = 30;
const G_STATUS = 7;
const G_SUCCESS_VALUE = 'Success';
const G_RECV_DATE = 2;
const G_TYPE = 10;
const G_REF_NUM = 11;
const G_AMOUNT = 6;

$batch_request_object = create_batch_request_object('PCOImport_'.date('c'));
$opts = create_opts($batch_request_object, $AppID, $secret);
$context = stream_context_create($opts);
$create_batch_result = json_decode(file_get_contents($create_batch_endpoint, false, $context), true);
$current_endpoint = $create_batch_result['data']['links']['donations'];

print_r($current_endpoint);
echo "\n";

$funds_index = [
    'Tithe' => '111',
    'Offerings' => '222',
    'Other' => '333',
    'World Missions' => '444'
];

$pco_ids = get_person_import_ids($people,$AppID,$secret);

foreach ($gifts as $donation) {
    $donation_fund = null;
    $donation_date = null;
    $donation_amount = null;
    $check_number = '00000';
    $donation_request_object = null;
    $pco_person_id = null;

    //Get first month
    $received_date = date('Y-m-d', strtotime($donation[G_RECV_DATE]));
    $pco_person_id = $pco_ids[$donation[G_PERSON_ID]];
    $donation_fund = $funds_index[$donation[G_FUND]];
    $donation_amount = (int) ((float) $donation[G_AMOUNT] * 100);

    switch (explode(' - ',$donation[G_TYPE])[0]) 
    {
        case 'Cash':
            $donation_request_object = create_cash_request_object($received_date, $pco_person_id, $donation_amount, $donation_fund);
            break;
        case 'ACH':
            if (null != $donation[G_REF_NUM]) $check_number = $donation[G_REF_NUM];
            $donation_request_object = create_ach_request_object($received_date, $check_number, $pco_person_id, $donation_amount, $donation_fund);
            break;
        case 'Check':
            if (null != $donation[G_REF_NUM]) $check_number = $donation[G_REF_NUM];
            $donation_request_object = create_check_request_object('Null', $check_number, $received_date, $pco_person_id, $donation_amount, $donation_fund);
            break;
        case 'Card':
            if (null != $donation[G_REF_NUM]) $check_number = $donation[G_REF_NUM];
            $donation_request_object = create_card_request_object(explode(' - ',$donation[G_TYPE])[1], $check_number, $received_date, $pco_person_id, $donation_amount, $donation_fund);
            break;
    }

    echo $donation[G_TRANS_ID] . " / " . $donation[G_PERSON_ID] . " - \n";

    if ($pco_person_id != null && $donation[G_STATUS] == G_SUCCESS_VALUE) {
        $opts = create_opts($donation_request_object, $AppID, $secret);
        $context = stream_context_create($opts);
        $person_file = file_get_contents($current_endpoint, false, $context);
        print_r($person_file);
    }
    echo "\n";
}

function get_person_import_ids($mypeople,$AppID,$secret)
{
    $return_array = [];
    foreach($mypeople as &$person)
    {
        $object = [];
        $opts = create_opts('', $AppID, $secret,'GET');
        $context = stream_context_create($opts);
        $output = json_decode(file_get_contents("https://api.planningcenteronline.com/people/v2/field_data?where[field_definition_id]=" . P_TAB_FIELD_ID . "&where[value]=" . $person[P_IND_ID], false, $context), true);
        if ($output['data'][0]['id'] != NULL){
            $return_array[$person[P_IND_ID]] = $output['data'][0]['relationships']['customizable']['data']['id'];
        }
        unset($output);
    }
    return $return_array;
}

function create_opts($request_object, $AppID, $secret, $direction='POST')
{
    $opts = [
        'http' => [
            'method' => $direction,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n".'Authorization: Basic '.base64_encode("$AppID:$secret"),
            'content' => $request_object,
        ],
    ];

    return $opts;
}

function create_cash_request_object($received_date, $person_pco_id, $donation_amount, $donation_fund)
{
    $request_object = json_encode(
    [
        'data' => [
            'type' => 'Donation',
            'attributes' => [
                'payment_method' => 'cash',
                'received_at' => $received_date, ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'type' => 'Person',
                        'id' => $person_pco_id,
                    ],
                ],
                'payment_source' => [
                    'data' => [
                        'type' => 'PaymentSource',
                        'id' => G_PAYMENT_SOURCE_ID,
                    ],
                ],
            ],
        ],
        'included' => [
            [
                'type' => 'Designation',
                'attributes' => [
                    'amount_cents' => $donation_amount,
                ],
                'relationships' => [
                    'fund' => [
                        'data' => [
                            'type' => 'Fund',
                            'id' => $donation_fund,
                        ],
                    ],
                ],
            ],
        ],
    ]
    );

    return $request_object;
}

function create_check_request_object($bank_name, $check_number, $donation_date, $person_pco_id, $donation_amount, $donation_fund)
{
    $request_object = json_encode(
    [
        'data' => [
            'type' => 'Donation',
            'attributes' => [
                'payment_method' => 'check',
                'payment_brand' => $bank_name . "- CK#" . $check_number,
                'payment_check_dated_at' => $donation_date,
                'received_at' => $donation_date, ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'type' => 'Person',
                        'id' => $person_pco_id,
                    ],
                ],
                'payment_source' => [
                    'data' => [
                        'type' => 'PaymentSource',
                        'id' => G_PAYMENT_SOURCE_ID,
                    ],
                ],
            ],
        ],
        'included' => [
            [
                'type' => 'Designation',
                'attributes' => [
                    'amount_cents' => $donation_amount,
                ],
                'relationships' => [
                    'fund' => [
                        'data' => [
                            'type' => 'Fund',
                            'id' => $donation_fund,
                        ],
                    ],
                ],
            ],
        ],
    ]
    );

    return $request_object;
}

function create_card_request_object($card_brand, $check_number, $donation_date, $person_pco_id, $donation_amount, $donation_fund)
{
    $request_object = json_encode(
    [
        'data' => [
            'type' => 'Donation',
            'attributes' => [
                'payment_method' => 'card',
                'payment_brand' => $card_brand . "- Acct#" . $check_number,
                'payment_method_sub' => 'credit',
                'payment_last4' => substr($check_number, -4),
                'received_at' => $donation_date, 
            ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'type' => 'Person',
                        'id' => $person_pco_id,
                    ],
                ],
                'payment_source' => [
                    'data' => [
                        'type' => 'PaymentSource',
                        'id' => G_PAYMENT_SOURCE_ID,
                    ],
                ],
            ],
        ],
        'included' => [
            [
                'type' => 'Designation',
                'attributes' => [
                    'amount_cents' => $donation_amount,
                ],
                'relationships' => [
                    'fund' => [
                        'data' => [
                            'type' => 'Fund',
                            'id' => $donation_fund,
                        ],
                    ],
                ],
            ],
        ],
    ]
    );

    return $request_object;
}

function create_ach_request_object($donation_date, $check_number, $pco_person_id, $donation_amount, $donation_fund)
{
    $request_object = json_encode(
    [
        'data' => [
            'type' => 'Donation',
            'attributes' => [
                'payment_method' => 'ach',
                'payment_brand' => 'ACH#' . $check_number,
                'received_at' => $donation_date, ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'type' => 'Person',
                        'id' => $pco_person_id,
                    ],
                ],
                'payment_source' => [
                    'data' => [
                        'type' => 'PaymentSource',
                        'id' => G_PAYMENT_SOURCE_ID,
                    ],
                ],
            ],
        ],
        'included' => [
            [
                'type' => 'Designation',
                'attributes' => [
                    'amount_cents' => $donation_amount,
                ],
                'relationships' => [
                    'fund' => [
                        'data' => [
                            'type' => 'Fund',
                            'id' => $donation_fund,
                        ],
                    ],
                ],
            ],
        ],
    ]
    );

    return $request_object;
}

function create_batch_request_object($batch_description)
{
    $request_object = json_encode(
    [
        'data' => [
            'type' => 'Batch',
            'attributes' => [
                'description' => $batch_description,
            ],
        ],
    ]
    );

    return $request_object;
}

echo '</pre>';
