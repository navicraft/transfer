<?php

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RateLimit
{
    public function __construct(
        public string $configuration = 'transfer_api' // Default to existing config name
    ) {
    }
}
