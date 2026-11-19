<?php

namespace App\Enums;

enum RunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
