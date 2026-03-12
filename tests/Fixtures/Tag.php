<?php

namespace Tests\Fixtures;

use Mitsuki\ORM\Attributes\Entity;

#[Entity]
class Tag
{
    public int $id;
    public string $label;
}
