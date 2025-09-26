<?php

namespace App;

enum TransactionMovementType: string
{
    case IN = 'IN';
    case OUT = 'OUT';
}
