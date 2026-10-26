<?php

namespace App\Services\Training;

enum LevelSource: string
{
    case FieldTest = 'field_test';
    case RaceResult = 'race_result';
    /** No anchor given — VMA seeded from the level profile (§4.2). */
    case Profile = 'profile';
}
