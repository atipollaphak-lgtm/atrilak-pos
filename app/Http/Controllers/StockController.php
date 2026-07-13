<?php

namespace App\Http\Controllers;

class StockController extends Controller
{
    public function index()
    {
        return view('stocks.index');
    }
}
