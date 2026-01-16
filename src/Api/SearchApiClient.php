<?php

namespace MyDigitalEnvironment\AlertsBundle\Api;

use Doctrine\ORM\EntityManagerInterface;
use MyDigitalEnvironment\AlertsBundle\Dto\Document;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiParametersDto;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiResult;
use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Enum\SearchApiMethods;
use MyDigitalEnvironment\MyDigitalEnvironmentBundle\Service\MyDigitalEnvironmentParameters;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

readonly class SearchApiClient
{
    private HttpClientInterface $client;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private MyDigitalEnvironmentParameters $parameters,
        private AccessDecisionManagerInterface $checker,
        HttpClientInterface $client,
    )
    {
        $this->client = $client->withOptions(
            (new HttpOptions())
                ->setBaseUri('https://search-api.openedition.org')
                ->setHeader('Accept', 'application/json')
                ->toArray()
        );
    }

    public function queryForDocuments(Search $search, SearchApiParametersDto $searchParameters = null): SearchApiResult
    {
        // todo: cache search results for better speed ? avoid spamming search api ?
        // todo : in future, send api request from the user computer (JavaScript/Ajax) ? If javascript is available
        //  unsure on that, may need to think more about it. Sending request from the user computer (ajax) should only be
        //  done for queries that won't be saved to the db

        $searchParameters ??= SearchApiParametersDto::fromEntity($search);
        return SearchApiResult::fromResponseData($this->client->request(
            'POST',
            SearchApiMethods::DOCUMENTS->value,
            ['json' => $searchParameters->toJsonArray()],
        )->toArray());
        // todo: format results to class
        // todo: proper error handling. right now we hope that nothing crash on the api side
    }

    /**
     * @param Search[] $searches
     * @return SearchApiResult[]
     */
    public function queriesForDocuments(array $searches, int $queryCap = Search::RESULT_CAP): array
    {
        /** @var SearchApiParametersDto[] $searchParameters */
        $searchParameters = array_map(fn(Search $s) => SearchApiParametersDto::fromEntity($s, documentsPerPage: $queryCap), $searches);

        /** @var ResponseInterface[] $responses */
        $responses = [];
        foreach ($searchParameters as $i => $searchParameter) {
            $responses[$i] = $this->client->request(
                'POST',
                SearchApiMethods::DOCUMENTS->value,
                ['json' => $searchParameter->toJsonArray()],
            );
        }

        // todo: better testing would be required to say which method would be the best. array_map or multiplexing ? Hard to say with a quick look.
        return array_map(fn(ResponseInterface $r) => SearchApiResult::fromResponseData($r->toArray()), $responses);

        // sorting is not working as desired
        // The solution below does NOT keep the SearchApiResult[] in the same order as the input Search[]
        // todo: test + find way of keeping correct sort order when multiplexing, maybe look in the parameters array of each SearchApiResult
        //  Would negate this the gains from multiplexing ? Is there any gains when multiplexing ?

        /** @noinspection PhpUnreachableStatementInspection */
        $documents = [];
        /**
         * @var ResponseInterface $response
         */
        foreach ($this->client->stream($responses) as $response => $chunk) {
            // todo: add error handling and timeout handling, see: https://symfony.com/doc/current/http_client.html#dealing-with-network-timeouts
            // if ($chunk->isFirst()){
            //     $d[] = $response->getHeaders();
            // }
            if ($chunk->isLast()) {
                $documents[spl_object_id($response)] = SearchApiResult::fromResponseData($response->toArray());
            }
        }

        ksort($documents);
        return array_values($documents);
    }

    /**
     * @param Search[] $searches
     * @param SearchApiResult[] $searchApiResults
     *
     * todo: remplace return array with a Class
     */
    public function updateResultCount(array $searches, array $searchApiResults, int $queryCap, \DateTime $date, bool $noLimit = false): array
    {
        $updateStatistic = [
            'updatedSearches' => 0,
            'newDocuments' => 0,
            // 'relatedUsers' => 0,
        ];

        foreach ($searches as $i => $search) {
            $emailSent = false;
            $this->logger->info("Updating search #{$search->getId()} - \"{$search->getDescription()}\"");
            $documents = $searchApiResults[$i]->documents;
            $this->setDefaultAnchorsIfMissing($documents, $search);


            $previousResultCount = $search->getNewResultCount();
            /** @var Document[] $newResults */
            [$newResults, $anchor, $isAnchorFound] = [[], $search->getUpdateAnchor(), false];
            // todo: split anchor into userAnchor and updateAnchor
            //  - userAnchor track which document was last seen by the user
            //  - updateAnchor track which document was last seen by the updater, user to only send the new documents
            //  There could be issue when using updateAnchor & search:debug:rewind-anchor, since there isn't any actual
            //    new documents when using the command
            //  By carefully changing how we change updateAnchor/userAnchor we could maybe simulate that behaviour
            //  Could implement a fake api endpoint for tests
            if ($documents === []) {
                $this->logger->notice("No result found for search #{$search->getId()} - \"{$search->getDescription()}\"");
            }
            foreach ($documents as $document) {
                if ($document->getUrl() === $anchor) {
                    $isAnchorFound = true;
                    break;
                }
                $newResults[] = $document;
            }

            // todo: could be good to upload in the DB a report for each & globally on the update, would allow better learning

            $pageLimit = (10 * $search->getFrequency()->value) - 1;
            $page = 2;
            while ($documents !== [] && (!$isAnchorFound) && ($noLimit || ($page < ($pageLimit + 2)))) {
                $this->logger->notice("Update anchor not found, checking page n°$page");
                $result = $this->queryForDocuments($search, SearchApiParametersDto::fromEntity($search, $page));
                foreach ($result->documents as $document) {
                    if ($document->getUrl() === $anchor) {
                        $isAnchorFound = true;
                        break;
                    }
                    $newResults[] = $document;
                }
                $page++;
            }

            $message = "found for search #{$search->getId()} - \"{$search->getDescription()}\"";
            $message = $isAnchorFound ? 'Anchor ' . $message : 'Anchor not ' . $message;
            $this->logger->log($isAnchorFound ? LogLevel::INFO : LogLevel::NOTICE, $message);

            $newResultCount = count($newResults);

            if ($newResultCount > 0) {
                $updateStatistic['newDocuments'] += $newResultCount;
                $this->logger->info("$newResultCount new results found for search #{$search->getId()} - \"{$search->getDescription()}\"");

                if ($this->parameters->canSendEmail() && $search->canSendEmail()) {
                    $subscriber = $search->getSubscriber();

                    $email = (new TemplatedEmail())
                        ->from($this->parameters->getAddress())
                        ->to(new Address($subscriber->getEmail(), $subscriber->getFullName()))
                        // todo: update subject when search is capped, ie: "More than 100 results for search..."
                        ->subject("[OpenEdition ENT] $newResultCount new result for search: {$search->getDescription()}")
                        ->htmlTemplate('@MyDigitalEnvironmentAlerts/emails/new_result.html.twig')
                        ->context([
                            'search' => $search,
                            'subscriber' => $subscriber,
                            'newResultCount' => $newResultCount,
                            'results' => $newResults,
                        ]);

                    // todo: use Messenger/Message Handler/new Message for better mail handling ?
                    //  do we want to stock up emails to be later sent at a specific time ? Could be a better solution.

                    // todo: add try/catch, implement a way to verify that the email was correctly sent, test what happen when mail can't be sent
                    $this->logger->info("Sending mail to {$subscriber->getEmail()} for search #{$search->getId()} - \"{$search->getDescription()}\"");
                    try {
                        $this->mailer->send($email);
                        $emailSent = true;
                    } catch (TransportExceptionInterface $e) {
                        $this->logger->error("TransportException, was unable of sending mail to {$subscriber->getEmail()}", [$e]);
                    }
                }

                $updateStatistic['updatedSearches']++;
            }

            // todo: add log when query is/was capped
            $wasQueryCapped = $search->isMoreResultThanCap();

            // todo: do we still use/need ->setQueryCap ? is it needed ? When I added it, it was with the thought that
            //  we could maybe configure the cap, now I'm not sure if it should still be there, leaning toward no
            // Update entity
            if ($search->canSendEmail()) {
                // todo: find another way to check/ensure all went well when no result were returned
                //  while $emailSent is useful, it does not work when an alert has no new result: no email is sent
                // if ($emailSent) {
                $document = $documents[0] ?? null;
                $search
                    ->setNewResultCount($newResultCount)
                    ->setMoreResultThanCap(!$isAnchorFound)
                    ->setUserAnchor($document?->getUrl())
                    ->setUpdateAnchor($document?->getUrl())
                    ->setQueryCap($queryCap)
                    ->setLastQueryDate($date)
                ;
            } else {
                // todo: try/catch or way to verify that it successfully queried, that there was no 500
                $search
                    ->setNewResultCount($wasQueryCapped && !$isAnchorFound ? $previousResultCount : $newResultCount + $previousResultCount)
                    ->setMoreResultThanCap(!$isAnchorFound || $wasQueryCapped)
                    ->setUpdateAnchor($documents[0]->getUrl())
                    ->setQueryCap($queryCap)
                    ->setLastQueryDate($date);
            }

            $this->entityManager->persist($search);
        }
        $this->entityManager->flush();
        return $updateStatistic;
    }

    public function resetAnchor(Search $search): void
    {
        $result = $this->queryForDocuments($search);
        $search
            ->setUpdateAnchor($result->documents !== [] ? $result->documents[0]->getUrl() : null)
            ->setUserAnchor($result->documents !== [] ? $result->documents[0]->getUrl() : null)
            ->setMoreResultThanCap(false)
            ->setNewResultCount(0)
            ->setQueryCap(Search::RESULT_CAP)
            ->setLastQueryDate(new \DateTime());
    }

    /** @param Document[] $documents */
    public function setDefaultAnchorsIfMissing(array $documents, Search $search): void
    {
        $userAnchor = $search->getUserAnchor();
        $updateAnchor = $search->getUpdateAnchor();
        if ($documents !== []) {
            if ($userAnchor === null) {
                $this->entityManager->persist(
                    $search->setUserAnchor($documents[0]->getUrl())
                        ->setNewResultCount(0)
                        ->setMoreResultThanCap(false)
                );
            }

            if ($updateAnchor === null) {
                $this->entityManager->persist(
                    $search->setUpdateAnchor($search->getUserAnchor() ?? $userAnchor ?? $documents[0]->getUrl())
                );
            }
        }
    }
}