<?php

namespace Mitsuki\ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(
        public string $targetEntity,
        public bool $nullable = true
    ) {}
}
