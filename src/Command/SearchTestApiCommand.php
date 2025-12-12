<?php

namespace MyDigitalEnvironment\AlertsBundle\Command;

use MyDigitalEnvironment\AlertsBundle\Api\SearchApiClient;
use MyDigitalEnvironment\AlertsBundle\Dto\SearchApiParametersDto;
use MyDigitalEnvironment\AlertsBundle\Enum\SearchApiMethods;
use MyDigitalEnvironment\AlertsBundle\Repository\SearchRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'search:query-api',
    description: 'Interact with the Search Api',
)]
class SearchTestApiCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly SearchApiClient $apiClient,
        private readonly SearchRepository $searchRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('alert', 'a', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Provide this option to fetch for a specified search alert', [])
            ->addArgument('method', InputArgument::OPTIONAL, "Search method\nEither \"documents\" or \"facets\"", 'documents', ['documents', 'facets'])
            ->addOption('query', null, InputOption::VALUE_REQUIRED, 'String to search in all fields', '*')
            ->addOption('advanced-query', null, InputOption::VALUE_REQUIRED, 'Advanced query string that allow to search in specific fields')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language & locale of the query', 'fr')
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'Current page', 1)
            ->addOption('documents-per-page', null, InputOption::VALUE_REQUIRED, 'Number of documents per page', 100)
            ->addOption('platform', 's' ,InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by platforms')
            ->addOption('translation', 'i', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by language')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, '[Facets] Get documents published after a year')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, '[Facets] Get documents published before a year')
            ->addOption('author', 'w', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by author')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by type')
            ->addOption('access', 'x', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by access')
            ->addOption('journals-publication', null, InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by journals when platform is [\'OJ\']')
            ->addOption('books-publication', null, InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by publishers when platform is [\'OB\']')
            ->addOption('hypotheses-publication', null, InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, '[Facets] Filter documents by blogs when platform is [\'CO\']')
            // todo : add sorting option
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $alerts = array_map(fn($d) => (int) $d, array_filter($input->getOption('alert'), fn(string $o) => ctype_digit($o)));
        if ($alerts !== []) {
            $page = is_int($page = $input->getOption('page')) ? $page : (ctype_digit($page) ? (int) $page : 1);

            foreach ($this->searchRepository->findAllByIds($alerts) as $search) {
                $parameters = SearchApiParametersDto::fromEntity($search, $page);
                $result = $this->apiClient->queryForDocuments($search, $parameters);
                dump($result);
            }

            return Command::SUCCESS;
        }

        $searchParameters = SearchApiParametersDto::fromInput($input);

        // todo: rework command to use search api client helper & verify that it still work as intended
        $client = $this->client->withOptions(
            (new HttpOptions())
                ->setBaseUri('https://search-api.openedition.org')
                ->setHeader('Accept', 'application/json')
                ->toArray()
        );

        $method = SearchApiMethods::tryFrom($input->getArgument('method'));
        if ($method === null) {
            $io->error(['Invalid method', "'{$input->getArgument('method')}' is not a valid method for the Search API"]);
            return Command::INVALID;
        }

        $response = $client->request('POST',
            $method === SearchApiMethods::FACETS ? SearchApiMethods::FACETS->value : SearchApiMethods::DOCUMENTS->value,
            ['json' => $searchParameters->toJsonArray()],
        );


        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaders()['content-type'][0];

        if ($statusCode === Response::HTTP_OK) {
            if ($contentType === 'application/json') {
                $d = $response->toArray();
                // if ($method === 'documents') {
                //     $c = count($d['documents']);
                //     $d['documents'] = "___{$c} documents___";
                // }
                dump($d);
            }
        }


        return Command::SUCCESS;
    }
}
