<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    //

    public function __construct(
        protected Client $cliente
    ) {}

    public function create() {}

    public function edit() {}

    public function consultAll(Request $request)
    {
        return $this->cliente->consultAll();
    }

    public function consultById() {}

    public function deleteById() {}
}
