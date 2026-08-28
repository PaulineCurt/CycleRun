<?php

namespace App\Services\Training;

enum LevelSource: string
{
    case FieldTest = 'field_test';
    case RaceResult = 'race_result';
}
