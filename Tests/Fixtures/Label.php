<?php

namespace Omnisearch\Typesense\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'label')]
class Label
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(#[ORM\Column] private string $slug)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function __toString(): string
    {
        return $this->slug;
    }
}
