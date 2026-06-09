<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioCentralController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => config('relatorios'),
        ]);
    }
}
