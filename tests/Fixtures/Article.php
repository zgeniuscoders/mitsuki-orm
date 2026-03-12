<?php

namespace Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Mitsuki\ORM\Attributes\Entity;
use Mitsuki\ORM\Attributes\ManyToMany;
use Mitsuki\ORM\Attributes\ManyToOne;

#[Entity]
class Article
{
    public int $id;
    public string $title;

    #[ManyToOne(targetEntity: Category::class)]
    public Category $category;

    #[ManyToMany(targetEntity: Tag::class)]
    public Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}
