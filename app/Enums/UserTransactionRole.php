<?php

namespace App\Enums;

enum UserTransactionRole: string
{
    case Sender = 'sender';
    case Reciver = 'reciver';
}
