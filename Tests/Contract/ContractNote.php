<?php

namespace Omnisearch\Typesense\Tests\Contract;

use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\Type;

/**
 * The document of omnisearch's conformance suite, as a Doctrine entity:
 * the bundle's transformer reads the identifier from Doctrine's metadata.
 * An integer one: the bundle sorts a collection by its primary identifier
 * when nothing else is said, and Typesense sorts no string.
 * Apart from Fixtures/, which the test application maps.
 */
#[ORM\Entity]
#[ORM\Table(name: 'contract_note')]
#[Index('contract_note')]
#[Field('title', type: Type::STRING)]
#[Field('rank', type: Type::INT, sort: true)]
#[Field('labels', type: Type::STRINGS, facet: true)]
class ContractNote
{
    /** @param list<string> $labels */
    public function __construct(
        #[ORM\Id] #[ORM\Column] private int $id,
        #[ORM\Column] private string $title,
        #[ORM\Column] private int $rank = 0,
        #[ORM\Column(type: 'json')] private array $labels = [],
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    /** @return list<string> */
    public function getLabels(): array
    {
        return $this->labels;
    }
}
