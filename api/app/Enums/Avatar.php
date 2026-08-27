<?php

namespace App\Enums;

/**
 * Predefined avatar set — keep in sync by hand with mobile's src/lib/avatars.ts
 * (same ids). No photo upload: a user can only pick one of these fixed keys.
 */
enum Avatar: string
{
    case Leaf = 'leaf';
    case Moon = 'moon';
    case Sun = 'sun';
    case Star = 'star';
    case Flower = 'flower';
    case Heart = 'heart';
    case Sparkles = 'sparkles';
    case Flame = 'flame';
}
