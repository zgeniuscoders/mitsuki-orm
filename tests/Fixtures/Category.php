<?php

namespace Tests\Fixtures;

use Mitsuki\ORM\Attributes\Entity;

#[Entity("categories")]
class Category
{
    public int $id;
    public string $name;
}
