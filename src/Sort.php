<?php

declare(strict_types=1);

namespace Amashukov\Etherscan;

enum Sort: string
{
    case Ascending  = 'asc';
    case Descending = 'desc';
}
