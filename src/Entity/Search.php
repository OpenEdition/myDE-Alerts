<?php

namespace MyDigitalEnvironment\AlertsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use MyDigitalEnvironment\AlertsBundle\Enum\DocumentAccess;
use MyDigitalEnvironment\AlertsBundle\Enum\Platform;
use MyDigitalEnvironment\AlertsBundle\Enum\UpdateFrequency;
use MyDigitalEnvironment\AlertsBundle\MyDigitalEnvironmentAlertsBundle;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use MyDigitalEnvironment\MyDigitalEnvironmentBundle\Entity\User;

// We stop using schema parameter, problems with postgresql and unsure on how to dynamically detect the DB for this use case

#[ORM\Table(name: MyDigitalEnvironmentAlertsBundle::TABLE_SCHEMA . '_search')]
#[ORM\Entity(repositoryClass: SearchRepository::class)]
class Search
{
    // todo : better name ?
    //  SearchAlert ? Alert ?

    const RESULT_CAP = 100;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $query = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $advancedQuery = null;

    /** @var Platform[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: Platform::class)]
    private array $platforms = [];

    /** @var DocumentAccess[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $access = [];

    #[ORM\Column(nullable: true)]
    private ?int $fromPublication = null;

    #[ORM\Column(nullable: true)]
    private ?int $untilPublication = null;

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $translations = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $authors = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $journalsPublications = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $booksPublications = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $hypothesesPublications = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $types = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $subscriber = null;

    /** @var ?string Identifier for the last result acknowledged by the user */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $userAnchor = null;

    /** @var ?string Identifier for the last result acknowledged by the server */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $updateAnchor = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $newResultCount = 0;

    /** @var bool Is there more result for this search than the API could find with a first call ? */
    #[ORM\Column(options: ['default' => false])]
    private bool $moreResultThanCap = false;

    /** @var ?int<0, max> How many documents was queries in last update query */
    #[ORM\Column(nullable: true)]
    private ?int $queryCap = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastQueryDate = null;

    #[ORM\Column(enumType: UpdateFrequency::class, options: ['default' => UpdateFrequency::DAILY])]
    private UpdateFrequency $frequency = UpdateFrequency::DAILY;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $sendEmail = true;

    public function __construct()
    {
    }

    public function getResultCounter(): string
    {
        $count = (string) $this->newResultCount;
        return $this->moreResultThanCap ? $count.'+' : $count;
    }

    public function getDescription(): string
    {
        return $this->name !== null
            ? $this->name
            : $this->getLongDescription();
    }

    public function getLongDescription(): string
    {
        $d = [];
        if ($this->query !== null) {
            $d[] = "q=\"$this->query\"";
        }
        if ($this->platforms !== []) {
            $d[] = "pf=" . join(',', array_map(fn($p) => $p->value, $this->platforms));
        }
        if ($this->fromPublication !== null) {
            $d[] = "from=$this->fromPublication";
        }
        if ($this->untilPublication !== null) {
            $d[] = "until=$this->untilPublication";
        }

        // todo: fall back or alert log if empty ?
        return join(' and ', $d);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function setQuery(?string $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function getAdvancedQuery(): ?string
    {
        return $this->advancedQuery;
    }

    public function setAdvancedQuery(?string $advancedQuery): static
    {
        $this->advancedQuery = $advancedQuery;

        return $this;
    }

    public function getPlatforms(): ?array
    {
        return $this->platforms;
    }

    public function setPlatforms(array $platforms): static
    {
        $this->platforms = $platforms;

        return $this;
    }

    public function getAccess(): ?array
    {
        return $this->access;
    }

    public function setAccess(array $access): static
    {
        $this->access = $access;

        return $this;
    }

    public function getFromPublication(): ?int
    {
        return $this->fromPublication;
    }

    public function setFromPublication(?int $fromPublication): static
    {
        $this->fromPublication = $fromPublication;

        return $this;
    }

    public function getUntilPublication(): ?int
    {
        return $this->untilPublication;
    }

    public function setUntilPublication(?int $untilPublication): static
    {
        $this->untilPublication = $untilPublication;

        return $this;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(array $translations): static
    {
        $this->translations = $translations;

        return $this;
    }

    public function getAuthors(): ?array
    {
        return $this->authors;
    }

    public function setAuthors(array $authors): static
    {
        $this->authors = $authors;

        return $this;
    }

    public function getJournalsPublications(): ?array
    {
        return $this->journalsPublications;
    }

    public function setJournalsPublications(?array $journalsPublications): static
    {
        $this->journalsPublications = $journalsPublications;

        return $this;
    }

    public function getBooksPublications(): ?array
    {
        return $this->booksPublications;
    }

    public function setBooksPublications(?array $booksPublications): static
    {
        $this->booksPublications = $booksPublications;

        return $this;
    }

    public function getHypothesesPublications(): ?array
    {
        return $this->hypothesesPublications;
    }

    public function setHypothesesPublications(?array $hypothesesPublications): static
    {
        $this->hypothesesPublications = $hypothesesPublications;

        return $this;
    }

    public function getTypes(): ?array
    {
        return $this->types;
    }

    public function setTypes(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function getSubscriber(): ?User
    {
        return $this->subscriber;
    }

    public function setSubscriber(?User $subscriber): static
    {
        $this->subscriber = $subscriber;

        return $this;
    }

    public function getUserAnchor(): ?string
    {
        return $this->userAnchor;
    }

    public function setUserAnchor(?string $userAnchor): static
    {
        $this->userAnchor = $userAnchor;

        return $this;
    }

    public function getNewResultCount(): ?int
    {
        return $this->newResultCount;
    }

    public function setNewResultCount(?int $newResultCount): static
    {
        $this->newResultCount = $newResultCount;

        return $this;
    }

    public function isMoreResultThanCap(): ?bool
    {
        return $this->moreResultThanCap;
    }

    public function setMoreResultThanCap(bool $moreResultThanCap): static
    {
        $this->moreResultThanCap = $moreResultThanCap;

        return $this;
    }

    public function getQueryCap(): ?int
    {
        return $this->queryCap;
    }

    public function setQueryCap(int $queryCap): static
    {
        $this->queryCap = $queryCap;

        return $this;
    }

    public function getLastQueryDate(): ?\DateTimeInterface
    {
        return $this->lastQueryDate;
    }

    public function setLastQueryDate(?\DateTimeInterface $lastQueryDate): static
    {
        $this->lastQueryDate = $lastQueryDate;

        return $this;
    }

    public function getFrequency(): UpdateFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(UpdateFrequency $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function canSendEmail(): ?bool
    {
        return $this->sendEmail;
    }

    public function setSendEmail(bool $sendEmail): static
    {
        $this->sendEmail = $sendEmail;

        return $this;
    }

    public function getUpdateAnchor(): ?string
    {
        return $this->updateAnchor;
    }

    public function setUpdateAnchor(string $updateAnchor): static
    {
        $this->updateAnchor = $updateAnchor;

        return $this;
    }
}
