<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Skipped = 'skipped';
    case Moved = 'moved';
}
