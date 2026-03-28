<?php

namespace App\Auth\MagicLink;

enum Action: string
{
    case AUTHENTICATE = 'AUTHENTICATE';
    case AUTHENTICATE_FLAGGED = 'AUTHENTICATE_FLAGGED';
    case STEP_UP = 'STEP_UP';
    case BLOCK = 'BLOCK';
}
