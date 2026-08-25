<?php

namespace App\Enums;

enum Flow: string
{
    case Spotting = 'spotting';
    case Light = 'light';
    case Medium = 'medium';
    case Heavy = 'heavy';
}
