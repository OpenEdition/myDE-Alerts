<?php

namespace MyDigitalEnvironment\AlertsBundle\MessageHandler;

use MyDigitalEnvironment\AlertsBundle\Api\SearchApiClient;
use MyDigitalEnvironment\AlertsBundle\Message\SearchUpdateMessage;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SearchUpdateMessageHandler
{
    public function __construct(
        private readonly SearchRepository $searchRepository,
        private readonly SearchApiClient $client,
        private readonly LoggerInterface $logger,
    )
    {
    }


    public function __invoke(SearchUpdateMessage $message): void
    {
        $date = new \DateTime();

        $criteria = [];
        if ($message->ids !== []) {
            $criteria['id'] = $message->ids;
        }

        $searches = $message->synchronize ? $this->searchRepository->findSynchronizedBy($criteria)
            : $this->searchRepository->findBy($criteria);

        // todo: test queriesForDocuments with big batches of Searches (100+)
        //  may need to have a way to splice them into smaller batches
        //  along with a way to better track progress in the CLI (progress bar?)
        // todo: warning! memory issue when too many alerts and/or when we ask for too many documents
        $searchApiResults = $this->client->queriesForDocuments($searches, $message->queryCap);

        // todo: find way to get a summary/number of searches with new result
        // todo: confirm that CRON is working, then implement mailcatcher + sending mail to the user email
        $updateStatistic = $this->client->updateResultCount($searches, $searchApiResults, $message->queryCap, $date, $message->noLimit);

        [$updatedSearches, $newDocuments, $searchesCount] = [$updateStatistic['updatedSearches'], $updateStatistic['newDocuments'], count($searches)];
        $updateMessage = $updatedSearches > 0
            ? "$newDocuments documents found for $updatedSearches searches out of $searchesCount"
            : "No new documents for $searchesCount searches";

        $this->logger->info('Searches Update result');
        $this->logger->info($updateMessage);

    }
}
