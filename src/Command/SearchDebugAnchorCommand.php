<?php

namespace MyDigitalEnvironment\AlertsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MyDigitalEnvironment\AlertsBundle\Api\SearchApiClient;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiParametersDto;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiResult;
use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'search:debug:rewind-anchor',
    description: 'Remind the anchor to simulate time passing and new documents being found',
)]
class SearchDebugAnchorCommand extends Command
{
    private array $resultCache = [];

    public function __construct(
        private SearchRepository $searchRepository,
        private SearchApiClient $client,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    public function queryForDocuments(Search $search, int $page): SearchApiResult
    {
        $cache = $this->resultCache[$page] ?? null;
        if ($cache === null) {
            $result = $this->client->queryForDocuments($search, SearchApiParametersDto::fromEntity($search, $page));
            $this->resultCache[$page] = $result;
        } else {
            $result = $cache;
        }
        return $result;
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Option description')
            ->addOption('id', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Option description', [])
            ->addOption('rewind', 'r', InputOption::VALUE_OPTIONAL, "by how far do we rewind the anchors", 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // todo: implement jitter for $rewind
        /** @var array $ids
         * @var bool $getAll
         */
        $ids = $input->getOption('id');
        $getAll = $input->getOption('all');
        $rewind = is_string($rewind = $input->getOption('rewind')) ?
            (ctype_digit($rewind) ? (int)$rewind : 2) :
            (is_int($rewind) ? $rewind : 2);

        $ids = array_map(fn($d) => (int)$d, array_filter($ids, fn($v) => ctype_digit($v)));

        if ($getAll) {
            /** @var Search[] $searches */
            $searches = $this->searchRepository->findAll();
        } else {
            $searches = $this->searchRepository->findAllByIds($ids);
        }

        foreach ($searches as $search) {
            $this->logger->info("Rewinding anchor of search #{$search->getId()} / {$search->getDescription()} by $rewind");
            $newUserAnchor = $this->searchAndUpdateForAnchor($search->getUserAnchor(), $search, $rewind);
            $newUpdateAnchor = $this->searchAndUpdateForAnchor($search->getUpdateAnchor(), $search, $rewind);
            dump([
                '$newUserAnchor' => $newUserAnchor,
                '$newUpdateAnchor' => $newUpdateAnchor,
            ]);
            if ($newUserAnchor !== null && $newUpdateAnchor !== null) {
                $this->logger->info("Updating entity");
                $this->entityManager->persist(
                    $search->setUpdateAnchor($newUpdateAnchor)
                        ->setUserAnchor($newUserAnchor)
                );
            } else {
                $this->logger->info("At least one anchor was null/not found");
            }
            $this->resultCache = [];
        }
        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    public function searchAndUpdateForAnchor(string $anchor, Search $search, int $rewind, int $page = 0, int $limit = 50): ?string
    {
        if ($page >= $limit) {
            $this->logger->notice('Hit limit for rewinding anchors');
            return null;
        }

        $newAnchor = null;
        $oldAnchorFound = false;
        $result = $this->queryForDocuments($search, ++$page);

        $documents = array_values($result->documents);
        foreach ($documents as $index => $document) {
            /** @var int $index */
            if ($oldAnchorFound = ($document->getUrl() === $anchor)) {
                $newIndex = ($index + $rewind);
                if (key_exists($newIndex, $documents)) {
                    $newAnchor = $documents[$newIndex]->getUrl();
                } else {
                    $offsetIndex = $newIndex % count($documents);
                    $offsetPage = intval($newIndex / count($documents)) + $page;
                    $offsetDocuments = $this->queryForDocuments($search, $offsetPage)->documents;
                    if ($offsetDocuments === []) {
                        break;
                    }
                    $newAnchor = $offsetDocuments[$offsetIndex]->getUrl();
                }
                break;
            }
        }

        if (!$oldAnchorFound) {
            $newAnchor = $this->searchAndUpdateForAnchor($anchor, $search, $rewind, $page, $limit);
        }

        return $newAnchor;
    }
}
