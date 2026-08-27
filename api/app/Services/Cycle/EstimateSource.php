<?php

namespace App\Services\Cycle;

enum EstimateSource: string
{
    case Measured = 'measured';
    case Declared = 'declared';
    case Default = 'default';
    case Override = 'override';
}
