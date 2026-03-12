<?php

namespace Mitsuki\ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public string $targetEntity,
        public bool $nullable = true,
        public array $cascade = []
    ) {}
}
