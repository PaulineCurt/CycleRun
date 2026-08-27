<?php

namespace App\Services\Training;

/**
 * Which progression model the plan runs on (§4.3 moteurB.md).
 *
 * `RunWalk` is the C25K-style course/marche protocol for a true beginner; it
 * transitions to continuous running internally once a sustained-run threshold is
 * held (the "bascule" — a parameter, not a hard-coded week).
 */
enum Track: string
{
    case RunWalk = 'run_walk';
    case Continuous = 'continuous';
}
