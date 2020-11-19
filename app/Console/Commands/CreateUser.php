<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 01 Sep 2020 21:30:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUser extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:user {username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {


        $user           = new User;
        $user->username = $this->argument('username');
        $user->save();


        return 0;


    }


}


