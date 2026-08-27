<?php

namespace App\Services\Training;

/**
 * Periodization blocks (§4.5 moteurB.md): base (EF volume) → development
 * (threshold/VMA introduced) → taper (affûtage).
 */
enum Mesocycle: string
{
    case Base = 'base';
    case Development = 'development';
    case Taper = 'taper';
}
