<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawl = 'withdrawl';
    case PeerToPeer = 'P2P';
}
