<?php

namespace MyDigitalEnvironment\AlertsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MyDigitalEnvironment\AlertsBundle\Api\SearchApiClient;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiParametersDto;
use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'search:resync',
    description: 'Reset the synchronization status of alerts', //todo: verify/expand on that description
    aliases: ['s:r']
)]
class SearchResyncCommand extends Command
{
    public function __construct(
        private readonly SearchRepository $searchRepository,
        private readonly SearchApiClient $client,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Id of Search entities to resync')
            ->addOption('all', description: 'Resync all Search entities')
            ->addOption('reset-emails', description: 'Resync reset the anchors for emails search alerts')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string[] $ids */
        $ids = $input->getOption('id');
        /** @var bool $all */
        $all = $input->getOption('all');
        /** @var bool $resetEmails */
        $resetEmails = $input->getOption('reset-emails');

        if ($ids !== [] && $all) {
            $io->error('You cannot use the options \'all\' and \'alert\' at the same time');
            return Command::INVALID;
        }

        /** @var int[] $ids */
        $ids = array_map(fn(string $d) => (int) $d, array_filter($ids, fn(string $s) => ctype_digit($s)));

        $searches = [];
        if ($all) {
            $searches = $this->searchRepository->findAll();
        } else if ($ids !== []) {
            $searches = $this->searchRepository->findAllByIds($ids);
        }

        foreach ($searches as $search) {
            $this->resyncSearch($search, $resetEmails);
        }

        return Command::SUCCESS;
    }

    private function resyncSearch(Search $search, bool $resetEmails = false): void
    {
        $this->logger->info("reSync for search #{$search->getId()}");
        $documents = $this->client->queryForDocuments($search)->documents;

        $this->client->setDefaultAnchorsIfMissing($documents, $search);
        $newDocumentCount = 0;
        $page = 2;
        [$isUpdateAnchorFound, $isUserAnchorFound] = [false, false];

        if ($resetEmails && $search->canSendEmail()) {
            $search->setUpdateAnchor($documents[0]->getUrl())
                ->setUserAnchor($documents[0]->getUrl());
        }

        if ($search->getUserAnchor() === $search->getUpdateAnchor()) {
            [$isUpdateAnchorFound, $isUserAnchorFound] = [true, true];
        }

        $i = 0;
        while (!$isUpdateAnchorFound || !$isUserAnchorFound) {
            if ($documents === []) {
                $this->logger->notice('documents array empty, stopping');
                break;
            }

            if (!$isUpdateAnchorFound && $isUserAnchorFound) {
                // todo maybe throw an exception to allow a future integration with a better logging system (save to logs to DB ?)
                //  unsure if needed/useful, as this isn't & shouldn't be a scheduled command
                $this->logger->critical('user anchor found before update anchor, this should not have happened', [$search, $documents]);
                break;
            }

            $document = $documents[$i++];
            $url = $document->getUrl();
            $gi = ($i - 1) + (($page - 2) * 100);

            if (!$isUpdateAnchorFound && $url === $search->getUpdateAnchor()) {
                $this->logger->debug("update anchor found - @$gi");
                $isUpdateAnchorFound = true;
            }

            if (!$isUserAnchorFound && $url === $search->getUserAnchor()) {
                $this->logger->debug("update anchor found - @$gi");
                $isUserAnchorFound = true;
            }

            if ($isUpdateAnchorFound && !$isUserAnchorFound) {
                $newDocumentCount++;
            }

            if ($i === count($documents)) {
                if (count($documents) !== Search::RESULT_CAP) {
                    break;
                } else {
                    $documents = $this->client->queryForDocuments($search, SearchApiParametersDto::fromEntity($search, $page++))->documents;
                    $i = 0;
                }
            }
        }

        if (!$isUpdateAnchorFound || !$isUserAnchorFound) {
            $this->logger->warning('Either update or user anchor not found', ['update' => $isUpdateAnchorFound, 'user' => $isUserAnchorFound]);
            return;
        }

        $this->logger->info("Updating search #{$search->getId()}: $newDocumentCount documents");
        $this->entityManager->persist(
            $search->setNewResultCount($newDocumentCount)
                ->setMoreResultThanCap(false)
        );
        $this->entityManager->flush();
    }
}
