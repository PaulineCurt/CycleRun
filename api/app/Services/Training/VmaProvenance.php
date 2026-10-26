<?php

namespace App\Services\Training;

/**
 * How well anchored the VMA behind a plan is — the refinement ladder of §6
 * (moteurB_three_levels.md): a profile default is overwritten as soon as a
 * stronger link exists, never the other way around.
 *
 *   Default  — the level profile's fallback VMA (no test given).
 *   Declared — a 6-minute field test or a race time entered in the wizard.
 *   Measured — reserved for a real completed race / derived from actual runs
 *              (drift re-plan, out of scope for this ticket).
 */
enum VmaProvenance: string
{
    case Default = 'default';
    case Declared = 'declared';
    case Measured = 'measured';
}
