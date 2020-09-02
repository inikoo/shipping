<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 14:51:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTenant extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:tenant {username} {slug} {data*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create user's tenant";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {


        $data=[];
        foreach ($this->argument('data') as $arg){
            if(preg_match('/(.+)=(.+)/',$arg,$matches)){

                $data[$matches[1]]=$matches[2];
            }
        }


        $tenant = new Tenant(
            [
                'slug' => $this->argument('slug'),
                'data' => $data

            ]
        );

        $user = (new User())->where('username', $this->argument('username'))->first();

        $user->tenants()->save($tenant);


        return 0;


    }


}


