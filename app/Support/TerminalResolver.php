<?php

namespace App\Support;

use Illuminate\Http\Request;

class TerminalResolver
{
    public static function fromRequest(Request $request): string
    {
        $terminal = trim((string) ($request->header('X-Terminal') ?: $request->input('terminal') ?: 'POS-01'));
        $terminal = preg_replace('/\s+/', ' ', $terminal) ?: 'POS-01';

        return substr($terminal, 0, 60);
    }
}
