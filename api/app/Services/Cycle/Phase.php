<?php

namespace App\Services\Cycle;

enum Phase: string
{
    case Menstrual = 'menstrual';
    case Follicular = 'follicular';
    case Ovulation = 'ovulation';
    case Luteal = 'luteal';
}
