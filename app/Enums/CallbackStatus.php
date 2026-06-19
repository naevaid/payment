<?php

namespace App\Enums;

enum CallbackStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
