<?php

namespace App\Enums;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Done = 'done';
    case Archived = 'archived';
}
