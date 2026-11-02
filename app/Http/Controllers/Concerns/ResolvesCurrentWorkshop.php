<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCurrentWorkshop
{
    protected function currentWorkshop(Request $request): Workshop
    {
        $workshop = $request->user()->currentWorkshop();

        if (! $workshop) {
            throw new HttpException(403, 'No workshop is linked to this account yet.');
        }

        return $workshop;
    }
}
