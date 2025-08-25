<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case deactivated = 'deactivated';
    case Suspended = 'suspended';
}
