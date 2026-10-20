<?php

namespace Omnisearch\Typesense\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\Type;

/**
 * A blog post declared by omnisearch's attributes, as a collection of
 * glitchr/typesense-bundle's configuration would be (MappingTest holds that
 * configuration): property paths, facets, infix, a sortable date, a field
 * of a method.
 */
#[ORM\Entity]
#[ORM\Table(name: 'post')]
#[Index('post')]
#[Field('state', type: Type::STRING, property: 'state', facet: true)]
#[Field('title', type: Type::STRINGS, property: 'title', infix: true)]
#[Field('tags', type: Type::STRINGS, property: 'tags.slug', infix: true, facet: true)]
#[Field('publishedAt', type: Type::DATETIME)]
#[Field('audio', type: Type::BOOL, property: 'isAudible', facet: true)]
#[Field('rank', type: Type::INT, sort: true, options: ['typesense' => ['optional' => true]])]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Label> */
    #[ORM\ManyToMany(targetEntity: Label::class, cascade: ['persist'])]
    private Collection $tags;

    public function __construct(
        #[ORM\Column] private string $title,
        #[ORM\Column] private \DateTime $publishedAt,
        #[ORM\Column] private string $state = 'publish',
        #[ORM\Column] private int $rank = 0,
        Label ...$tags,
    ) {
        $this->tags = new ArrayCollection($tags);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function getPublishedAt(): \DateTime
    {
        return $this->publishedAt;
    }

    /** @return Collection<int, Label> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function isAudible(): bool
    {
        return str_contains(strtolower($this->title), 'music');
    }
}
