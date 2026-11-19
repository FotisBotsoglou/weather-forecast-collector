<?php

namespace App\Weather;

enum CollectOutcome: string
{
    case Collected = 'collected';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
