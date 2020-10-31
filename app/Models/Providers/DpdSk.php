<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 17:07:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\Shipment;
use App\Models\ShipperAccount;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use ReflectionException;
use Yasumi\Yasumi;
use Illuminate\Support\Str;


/**
 * Class DpdSk
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdSk extends Shipper_Provider {


    protected $table = 'dpd_sk_shipper_providers';

    protected string $api_url = "https://api.dpdportal.sk/shipment";

    protected $credentials_rules = [
        'apiKey'   => ['required'],
        'username' => [
            'required',
            'email'
        ],
        'delisId'  => ['required'],
        'pickupID' => ['required'],
        'bankID'   => [],

    ];


    public function createLabel(Shipment $shipment, Request $request, ShipperAccount $shipperAccount) {

        $params = array(
            'jsonrpc' => '2.0',
            'method'  => 'create',
            'params'  => array(
                'DPDSecurity' => array(
                    'SecurityToken' => array(
                        'ClientKey' => $shipperAccount->credentials['apiKey'],
                        'Email'     => $shipperAccount->credentials['username'],
                    ),
                ),
                'shipment'    => [$this->get_shipment_parameters($request, $shipperAccount)],
            ),
            'id'      => 'null',
        );

        $shipment->request = $params['shipment'];
        $shipment->save();

        $apiResponse = $this->call_api(
            $this->api_url, ["Content-Type: application/json"], json_encode($params)
        );

        $shipment->response = $apiResponse['data'];
        $shipment->status   = 'error';

        $result = [];
        if (!empty($apiResponse['data']['error'])) {

            if ($apiResponse['data']['error']['code'] == 103) {
                $result['errors'][] = ['authentication' => $apiResponse['data']['error']['message']];
                $result['status']   = 401;
            } elseif ($apiResponse['data']['error']['code'] == 401 or $apiResponse['data']['error']['code'] == 519) {

                $msg = $apiResponse['data']['error']['message'];


                if (isset($apiResponse['data']['error']['data']['additional_details'])) {
                    $msg .= ' '.$apiResponse['data']['error']['data']['additional_details'];
                }

                $result['errors'][] = ['invalid' => $msg];
                $result['status']   = 422;
            }


        } else {

            $res = array_pop($apiResponse['data']['result'])[0];
            if (!$res['success']) {
                $result['status'] = 422;
                foreach ($res['messages'] as $error) {

                    $result['errors'][] = [$res['ackCode'] => $error['value'].' ('.$error['envelope'].($error['element'] != '' ? '.'.$error['element'] : '').')'];

                }
            } else {
                $shipment->status          = 'success';
                $result['tracking_number'] = substr($res['mpsid'], 0, -8);
                $result['label_link']      = $res['label'];
                $result['shipment_id']     = $shipment->id;

            }
        }
        $shipment->save();

        return $result;


    }

    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        foreach ($parcelsData as $key => $value) {
            if ($value['weight'] > 31.5) {
                $parcelsData[$key]['weight'] = '31.5';
            } elseif ($value['weight'] < 0.1) {
                $parcelsData[$key]['weight'] = '0.1';
            }
        }


        try {
            $pickup_date = new Carbon(Arr::get($pickUp, 'date'));
        } catch (Exception $e) {
            $pickup_date = new Carbon();
        }

        $pickUpTimeWindow = [];

        if (Arr::get($pickUp, 'start')) {
            $pickUpTimeWindow['beginning'] = preg_replace('/:/', '', Arr::get($pickUp, 'start'));
        }
        if (Arr::get($pickUp, 'end')) {
            $pickUpTimeWindow['end'] = preg_replace('/:/', '', Arr::get($pickUp, 'end'));
        }
        if ($pickUpTimeWindow == []) {
            $pickUpTimeWindow['end'] = '1600';
        }

        if (Arr::get($shipTo, 'organization') != '') {
            $type       = 'b2b';
            $name       = Arr::get($shipTo, 'organization');
            $nameDetail = Arr::get($shipTo, 'contact');
        } else {
            $type       = 'b2c';
            $name       = Arr::get($shipTo, 'contact');
            $nameDetail = '';
        }

        $country = (new Country)->where('code', $shipTo['country_code'])->first();

        $services = [];


        if (!empty($cash_on_delivery)) {

            $order_id = preg_replace("/[^0-9]/", "", $request->get('reference'));
            if ($order_id == '') {
                $order_id = rand(1, 100);
            }

            if (Arr::get($cash_on_delivery, 'accept_card', 'No') == 'Yes') {
                $paymentMethod = 1;
            } else {
                $paymentMethod = 0;
            }

            $services = [
                'cod' => [
                    'amount'         => $cash_on_delivery['amount'],
                    'currency'       => $cash_on_delivery['currency'],
                    'bankAccount'    => [
                        'id' => $shipperAccount->credentials['bankID'],
                    ],
                    'variableSymbol' => $order_id,
                    'paymentMethod'  => $paymentMethod,
                ]
            ];
        }

        $postcode = trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code'));
        if (!in_array(
            $country->code, [
            'GB',
            'NL'
        ]
        )) {
            $postcode = preg_replace("/[^0-9]/", '', $postcode);
        }

        if ($country->code == 'IE') {
            $postcode = '';
        }

        $reference = preg_replace("/[^A-Za-z0-9]/", '', $request->get('reference'));


        $street       = preg_replace("/&/", ' ', Arr::get($shipTo, 'address_line_1'));
        $streetDetail = preg_replace("/&/", '', Arr::get($shipTo, 'address_line_2'));


        $street       = preg_replace("/²/", '2', $street);
        $streetDetail = preg_replace("/²/", '2', $streetDetail);


        $street       = preg_replace("/'/", '', $street);
        $streetDetail = preg_replace("/'/", '', $streetDetail);

        $street       = preg_replace("/`/", '', $street);
        $streetDetail = preg_replace("/`/", '', $streetDetail);

        $street       = preg_replace("/\"/", '', $street);
        $streetDetail = preg_replace("/\"/", '', $streetDetail);

        $street       = preg_replace("/Ø/", 'ø', $street);
        $streetDetail = preg_replace("/Ø/", 'ø', $streetDetail);


        $streetDetail = Str::limit($streetDetail, 35);


        $phone = trim(Arr::get($shipTo, 'phone'));
        if (!preg_match('/^\+/', $phone) and $phone != '') {
            $phone = '+'.$phone;
        }

        $pickup_date = $this->get_pick_up_date($pickup_date);

        return array(
            'reference'        => $reference,
            'delisId'          => $shipperAccount->credentials['delisId'],
            'note'             => Str::limit($request->get('note'), 35),
            'product'          => $request->get('shipping_product', 1),
            'pickup'           => array(
                'date'       => $pickup_date->format('Ymd'),
                'timeWindow' => $pickUpTimeWindow
            ),
            'addressSender'    => array(
                'id' => $shipperAccount->credentials['pickupID'],
            ),
            'addressRecipient' => array(
                'type'         => $type,
                'name'         => $name,
                'nameDetail'   => $nameDetail,
                'street'       => $street,
                'streetDetail' => $streetDetail,
                'zip'          => $postcode,
                'country'      => $country->code_iso_numeric,
                'city'         => Arr::get($shipTo, 'locality'),
                'phone'        => $phone,
                'email'        => Arr::get($shipTo, 'email'),

            ),
            'parcels'          => ['parcel' => $parcelsData],
            'services'         => $services
        );


    }

    private function get_pick_up_date($pickup_date) {
        if ($pickup_date->isWeekend() or $this->is_bank_holiday($pickup_date)) {
            return $this->get_pick_up_date($pickup_date->addDay());
        }

        return $pickup_date;

    }


    private function is_bank_holiday($date) {

        $formatted_date = $date->format('Y-m-d');

        try {
            $holidays = Yasumi::create('Slovakia', $date->format('Y'));
            foreach ($holidays as $day) {
                if ($day == $formatted_date and in_array(
                        $day->getType(), [
                        'bank',
                        'official'
                    ]
                    )) {
                    return true;
                }
            }

            return false;

        } catch (ReflectionException $e) {
            return false;
        }


    }

}

