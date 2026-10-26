<?php

namespace App\Enums;

/**
 * Declared runner level (§4.1 moteurB_three_levels.md).
 *
 * The level is *declared*, never inferred by an algorithm. It only selects a set
 * of starting values (a LevelProfile) — it recomputes no methodology. Persisted
 * on the program and editable afterwards.
 */
enum Level: string
{
    case Debutante = 'debutante';
    case Intermediaire = 'intermediaire';
    case Confirmee = 'confirmee';
}
