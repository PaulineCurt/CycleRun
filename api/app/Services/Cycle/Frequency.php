<?php

namespace App\Services\Cycle;

enum Frequency: string
{
    case Normal = 'normal';
    case Infrequent = 'infrequent';
    case Frequent = 'frequent';
    case Unknown = 'unknown';
}
