<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 03 Sep 2020 21:09:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Http\Controllers;

use App\Models\Shipper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class ShipperAccountController extends Controller {


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request) {


        $validator = Validator::make($request->all(), [
            'tenant' => [
                'required',
                Rule::exists('tenants','slug')->where(function ($query) {
                    $query->where('user_id', auth()->user()->id);
                }),
            ],
            'shipper' => [
                'required',
                'exists:shippers,slug',
                'unique:shipper_accounts,slug'
            ],
            'label' => [
                'required'
            ],
        ]);


        if ($validator->fails()) {
            return response()->json(['errors'=>$validator->errors()],422);
        }




        $shipper= (new Shipper)->where('slug', $request->get('shipper'))->first();

        print_r($shipper);

        try {
            $shipper_account = $shipper->provider->createShipperAccount($request);
            return response()->json(['shipper-account-id'=>$shipper_account->id]);
        } catch (Exception $e) {


            return response()->json(['errors'=>$e->getMessage()],400);
        }


    }


}
