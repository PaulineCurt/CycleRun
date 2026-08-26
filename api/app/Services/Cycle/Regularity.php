<?php

namespace App\Services\Cycle;

enum Regularity: string
{
    case Regular = 'regular';
    case Irregular = 'irregular';
    case Unknown = 'unknown';
}
