<?php

namespace MyDigitalEnvironment\AlertsBundle\Controller;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use MyDigitalEnvironment\AlertsBundle\Api\SearchApiClient;
use MyDigitalEnvironment\AlertsBundle\Dto\Document;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiParametersDto;
use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Enum\SearchFormMode;
use MyDigitalEnvironment\AlertsBundle\Form\ApiSearchType;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use MyDigitalEnvironment\MyDigitalEnvironmentBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('IS_AUTHENTICATED')]
class SearchController extends AbstractController
{
    public function __construct(private SearchApiClient $client)
    {
    }


    public function hub(SearchRepository $searchRepository, KernelInterface $kernel): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = new DateTime();

        $searches = $searchRepository->findAllByUser($user);
        if ($searches === []) {
            return $this->redirectToRoute('my_digital_environment_alerts_new_search');
        }

        // if ($kernel->getEnvironment() === 'vm') { // todo: replace with env variable
        //     $searchApiResults = $this->client->queriesForDocuments($searches);
        //     $this->client->updateResultCount($searches, $searchApiResults, Search::RESULT_CAP, $date);
        // }

        return $this->render('@MyDigitalEnvironmentAlerts/search/hub.html.twig', [
            'searches' => $searches,
        ]);
    }

    public function newSearch(Request $request): Response
    {
        $form = $this->createForm(ApiSearchType::class, new Search());
        $form->handleRequest($request);

        return $this->render('@MyDigitalEnvironmentAlerts/search/new.html.twig', [
            'form' => $form,
        ]);
    }

    public function saveSearch(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ApiSearchType::class, new Search());

        // $d = $form->getData();
        // dump($form);
        // dump($form->getData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Search $searchForm */
            $searchForm = $form->getData();
            // todo: validate user own the search entity. New method in search repository ?

            if ($searchForm->getId() !== null) {
                /** @var Search $search */
                $search = $entityManager->getRepository(Search::class)->find($searchForm->getId());

                // todo: check/verify how array compare is done for platforms, are the platforms always set in the same order ?
                //  do we need to sort the arrays first ?
                $sensitiveFieldChanged =
                    ($searchForm->getQuery() !== $search->getQuery()) ||
                    ($searchForm->getPlatforms() !== $search->getPlatforms()) ||
                    ($searchForm->canSendEmail() !== $search->canSendEmail());

                $search->setName($searchForm->getName())
                    ->setQuery($searchForm->getQuery())
                    ->setPlatforms($searchForm->getPlatforms())
                    ->setFrequency($searchForm->getFrequency())
                    ->setSendEmail($searchForm->canSendEmail())
                    ->setUntilPublication($searchForm->getUntilPublication())
                    ->setFromPublication($searchForm->getFromPublication())
                ;

                if ($sensitiveFieldChanged) {
                    $this->client->resetAnchor($search);
                }
            } else {
                $searchForm->setSubscriber($this->getUser());
                $this->client->resetAnchor($searchForm);
                $entityManager->persist($searchForm);
            }

            $entityManager->flush();
            return $this->redirectToRoute('my_digital_environment_alerts_hub');
        }

        // todo: replace with valid
        return $this->redirectToRoute('my_digital_environment_alerts_hub');
    }

    public function getSearchResults(Request $request, Search $search, EntityManagerInterface $entityManager): Response
    {
        $anchorChange = !$request->query->has('_noAnchorChange');
        $pageLimit = 10;
        $pageCount = 0;
        $isAnchorFound = false;
        $anchor = $search->getUserAnchor();
        /** @var Document[] $documents */
        $documents = [];

        do {
            $results = $this->client->queryForDocuments(
                $search,
                SearchApiParametersDto::fromEntity($search, ++$pageCount),
            )->documents;

            for ($i = 0; $i < count($results); $i++) {
                $document = $results[$i];
                if ($document->getUrl() === $anchor) {
                    $isAnchorFound = true;
                    break;
                }
                $documents[] = $document;
            }

            // todo: stop when $results is empty
            // dump($results);
        } while (!$isAnchorFound and ($pageCount < $pageLimit));

        if ($anchorChange && $documents !== []) {
            $entityManager->persist(
                $search->setUserAnchor($documents[0]->getUrl())
                    ->setUpdateAnchor($documents[0]->getUrl())
                    ->setNewResultCount(0)
                    ->setMoreResultThanCap(0)
            );
            $entityManager->flush();
        }

        return $this->render('@MyDigitalEnvironmentAlerts/search/results.html.twig', [
            'search' => $search,
            'documents' => $documents,
        ]);
    }

    public function previewResults(Request $request, HttpClientInterface $client, SearchRepository $searchRepository, ?int $id): Response
    {
        $method = $request->getMethod();
        if ($method === 'GET' && $id !== null) {
            $search = $searchRepository->find($id);
        } elseif ($method === 'POST') {
            // todo : validate that request is a POST request from SearchController::newSearch ?
            $form = $this->createForm(ApiSearchType::class, new Search());

            $form->handleRequest($request);
            if (!$form->isSubmitted() || !$form->isValid()) {
                return $this->redirectToRoute('my_digital_environment_alerts_new_search', [], 307);
            }

            // Form is submitted and valid past this point
            /** @var Search $search */
            $search = $form->getData();
        } else {
            // todo: add error handling, remove redirect or replace with clearer page to show invalid request
            return $this->redirectToRoute('my_digital_environment_alerts_hub');
        }

        $searchResults = $this->client->queryForDocuments($search);

        // todo : add error checking, to check in case search api is down or send back an error (status code not 200)

        $form = $this->createForm(ApiSearchType::class, $search, ['search_form_mode' => SearchFormMode::RESULTS]);
        return $this->render('@MyDigitalEnvironmentAlerts/search/preview.html.twig', [
            'search' => $search,
            'result' => $searchResults,
            'form' => $form,
        ]);
    }

    public function editSearch(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // todo: is it possible to simply this search ?
        // todo: add security to make it so that user can only edit their own search. New method in repository ?
        $search = $entityManager->getRepository(Search::class)->findOneBy(['id' => $id]);
        if ($search === null) {
            return $this->redirectToRoute('my_digital_environment_alerts_hub');
        }

        $form = $this->createForm(ApiSearchType::class, $search);
        $form->handleRequest($request);
        // if ($form->isSubmitted() && $form->isValid()) {
        //     $form->getData();
        //     $entityManager->flush();
        //     return $this->redirectToRoute('my_digital_environment_alerts_hub');
        // }

        return $this->render('@MyDigitalEnvironmentAlerts/search/new.html.twig', [
            'form' => $form,
        ]);
    }

    public function deleteSearch(EntityManagerInterface $entityManager, int $id): Response
    {
        /** @var Search $search */
        $search = $entityManager->getRepository(Search::class)->findOneBy(['id' => $id]);

        if ($search !== null) {
            $entityManager->remove($search);
            $entityManager->flush();
        }

        return $this->redirectToRoute('my_digital_environment_alerts_hub');
    }
}
