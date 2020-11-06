<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:39:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\PdfLabel;
use App\Models\Shipment;
use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use SoapClient;
use stdClass;

/**
 * Class GlsSk
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlsSk extends Shipper_Provider {

    protected $table = 'gls_sk_shipper_providers';

    protected string $api_url = "https://api.mygls.sk/ParcelService.svc?singleWsdl";

    protected $credentials_rules = [
        'username'      => [
            'required',
            'email'
        ],
        'password'      => ['required'],
        'client_number' => [
            'required',
            'numeric'
        ],

    ];

    public function createLabel(Shipment $shipment,Request $request, ShipperAccount $shipperAccount) {

        $debug=Arr::get($shipperAccount->data, 'debug') == 'Yes';

        $printLabelsRequest = array(
            'Username'   => $shipperAccount->credentials['username'],
            'Password'   => hex2bin($shipperAccount->credentials['password']),
            'ParcelList' => $this->getShipmentParameters($request, $shipperAccount)
        );



        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.request',json_decode(json_encode($printLabelsRequest['ParcelList']), true));
            $shipment->data=$shipmentData;
            $shipment->save();
        }


        $request = array("printLabelsRequest" => $printLabelsRequest);

        $soapOptions = array(
            'soap_version'   => SOAP_1_1,
            'stream_context' => stream_context_create(array('ssl' => array('cafile' => '../assets/ca_cert.pem')))
        );


        try {
            $client = new SoapClient($this->api_url, $soapOptions);
        } catch (SoapFault $e) {
            $result['errors'] = ['Soap API connection error'];
            return $result;
        }

        $apiResponse = $client->PrintLabels($request)->PrintLabelsResult;


        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.response', json_decode(json_encode($apiResponse), true));
            $shipment->data=$shipmentData;
        }
        $shipment->status   = 'error';


        $result = [
            'shipment_id' => $shipment->id
        ];

        if (count((array)$apiResponse->PrintLabelsErrorList)) {

            $result['errors'] = [$apiResponse->PrintLabelsErrorList];
            $result['status']        = 599;


            $msg=$apiResponse->PrintLabelsErrorList->ErrorInfo->ErrorDescription;
            $result['error_message'] = $msg;
            $shipment->reference_3 = $msg;


        } elseif ($apiResponse->Labels != "") {

            $pdfData     = $apiResponse->Labels;
            $pdfChecksum = md5($pdfData);
            $pdfLabel    = new PdfLabel(
                [
                    'checksum' => $pdfChecksum,
                    'data'     => base64_encode($pdfData)
                ]
            );
            $shipment->pdf_label()->save($pdfLabel);
            $shipment->status = 'success';

            $result['tracking_number'] = $apiResponse->PrintLabelsInfoList->PrintLabelsInfo->ParcelNumber;
            $result['shipment_id']     = $shipment->id;
            $result['label_link']      = env('APP_URL').'/labels/'.$pdfChecksum;

        }

        $shipment->save();


        return $result;

    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        $parcels = [];
        $parcel  = new StdClass();

        $tenant = $shipperAccount->tenant;

        $shipTo = array_filter($shipTo);

        $services = [];
        if (!empty($cash_on_delivery)) {
            $parcel->CODAmount    = $cash_on_delivery['amount'];
            $parcel->CODReference = $request->get('reference');
            $service1             = new StdClass();
            $service1->Code       = "COD";
            $services[]           = $service1;
        }


        $tenant_postal_code = trim(Arr::get($tenant->data['address'], 'sorting_code').' '.Arr::get($tenant->data['address'], 'postal_code'));
        if (Arr::get($tenant->data['address'], 'country_code') == 'SK') {
            $tenant_postal_code = preg_replace('/SK-?/i', '', $tenant_postal_code);
        }


        $parcel->ClientNumber            = $shipperAccount->credentials['client_number'];
        $parcel->ClientReference         = $request->get('reference');
        $parcel->Content                 = $request->get('note');
        $parcel->Count                   = count($parcelsData);
        $deliveryAddress                 = new StdClass();
        $deliveryAddress->ContactEmail   = Arr::get($shipTo, 'email');
        $deliveryAddress->ContactName    = Arr::get($shipTo, 'contact');
        $deliveryAddress->ContactPhone   = Arr::get($shipTo, 'phone');
        $deliveryAddress->Name           = Arr::get($shipTo, 'organization', Arr::get($shipTo, 'contact'));
        $deliveryAddress->Street         = trim(Arr::get($shipTo, 'address_line_1').' '.Arr::get($shipTo, 'address_line_2'));
        $deliveryAddress->City           = Arr::get($shipTo, 'locality');
        $deliveryAddress->ZipCode        = trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code'));
        $deliveryAddress->CountryIsoCode = Arr::get($shipTo, 'country_code');
        $parcel->DeliveryAddress         = $deliveryAddress;
        $pickupAddress                   = new StdClass();
        $pickupAddress->ContactName      = Arr::get($tenant->data, 'contact');
        $pickupAddress->ContactPhone     = Arr::get($tenant->data, 'phone');
        $pickupAddress->ContactEmail     = Arr::get($tenant->data, 'email');
        $pickupAddress->Name             = Arr::get($tenant->data, 'organization');
        $pickupAddress->Street           = trim(Arr::get($tenant->data['address'], 'address_line_1').' '.Arr::get($tenant->data['address'], 'address_line_2'));
        $pickupAddress->City             = Arr::get($tenant->data['address'], 'locality');
        $pickupAddress->ZipCode          = $tenant_postal_code;
        $pickupAddress->CountryIsoCode   = Arr::get($tenant->data['address'], 'country_code');
        $parcel->PickupAddress           = $pickupAddress;
        $parcel->PickupDate              = gmdate('Y-m-d');
        $parcel->ServiceList             = $services;

        $parcels[] = $parcel;


        return $parcels;


    }


}
