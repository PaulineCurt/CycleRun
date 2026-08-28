<?php

namespace App\Enums;

enum SessionType: string
{
    case RunWalk = 'run_walk';
    case Easy = 'easy';
    case Tempo = 'tempo';
    case Threshold = 'threshold';
    case Interval = 'interval';
    case Long = 'long';
    case Rest = 'rest';
    case Cross = 'cross';
}
