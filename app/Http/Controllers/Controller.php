<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * A GET filter value as a string, or null when absent or not a scalar string.
     * A crafted `?q[]=x` arrives as an array; treating it as no filter keeps the
     * "nonsense filters never error" rule instead of a 500 in the query or view.
     */
    protected function filterValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }
}
