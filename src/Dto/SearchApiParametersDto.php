<?php

namespace MyDigitalEnvironment\AlertsBundle\Dto;

use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Enum\SearchApiSortKey;
use Symfony\Component\Console\Input\InputInterface;

readonly final class SearchApiParametersDto
{
    // todo : convert to core dto/array encapsulation ?
    // May be better for some parameters if we left any unset values as null instead of giving default
    /**
     * @param string[] $platforms
     * @param string[] $translations
     * @param string[] $authors
     * @param string[] $types
     * @param string[] $access
     * @param string[] $journalsPublications
     * @param string[] $booksPublications
     * @param string[] $hypothesesPublications
     * @param array<string, string> $sort
     */
    public function __construct(
        public string $query = '*',
        public ?string $advancedQuery = null,
        public string $lang = 'fr',
        public int $page = 1,
        public int $documentsPerPage = 10,
        public array $platforms = [],
        public array $translations = [],
        public string|int|null $from = null,
        public string|int|null $to = null,
        public array $authors = [],
        public array $types = [],
        public array $access = [],
        public array $journalsPublications = [],
        public array $booksPublications = [],
        public array $hypothesesPublications = [],
        public array $sort = [SearchApiSortKey::DATE_OF_UPLOAD->value => 'desc'],
    )
    {
    }

    public static function fromInput(InputInterface $input): SearchApiParametersDto
    {
        $options = $input->getOptions();
        return new SearchApiParametersDto(
            $options['query'],
            $options['advanced-query'],
            $options['lang'],
            $options['page'],
            $options['documents-per-page'],
            $options['platform'],
            $options['translation'],
            $options['from'],
            $options['to'],
            $options['author'],
            $options['type'],
            $options['access'],
            $options['journals-publication'],
            $options['books-publication'],
            $options['hypotheses-publication'],
        );
    }

    public static function fromEntity(Search $search, int $page = 1, int $documentsPerPage = Search::RESULT_CAP, string $lang = 'fr'): SearchApiParametersDto
    {
        // todo : add sorting option
        return new SearchApiParametersDto(
            $search->getQuery() ?? '*',
            $search->getAdvancedQuery(),
            $lang,                                                                                                             // $options['locale'],
            $page,                                                                                                             // $options['page'],
            $documentsPerPage,                                                                                                 // $options['documents-per-page'],
            (($platforms = $search->getPlatforms()) !== null) ? array_map(fn($platform) => $platform->value, $platforms) : [], // $options['platform'],
            $search->getTranslations() ?? [],                                                                                  // $options['translation'],
            $search->getFromPublication(),                                                                 // $options['from'],
            $search->getUntilPublication(),                                                                // $options['to'],
            $search->getAuthors() ?? [],                                                                                       // $options['author'],
            $search->getTypes() ?? [],                                                                                         // $options['type'],
            ($access = $search->getAccess()) ? array_map(fn($acc) => $acc->value, $access) : [],                               // $options['access'],
            $search->getJournalsPublications() ?? [],                                                                          // $options['journals-publication'],
            $search->getBooksPublications() ?? [],                                                                             // $options['books-publication'],
            $search->getHypothesesPublications() ?? [], // $options['hypotheses-publication'],
        );
    }

    public function toJsonArray(): array
    {
        // todo : add sorting option
        $array = [
            'q' => $this->query,
            'lang' => $this->lang,
            'locale' => $this->lang,
            'pagination' => [
                'currentPage' => $this->page,
                'documentsPerPage' => $this->documentsPerPage,
            ],
            'facets' => [
                'platform' => ['options' => $this->platforms],
                'access' => ['options' => $this->access],
                'type' => ['options' => $this->types],
                'author' => ['options' => $this->authors],
                'translations' => ['options' => $this->translations],
                'journals-publication' => ['options' => $this->journalsPublications],
                'books-publication' => ['options' => $this->booksPublications],
                'hypotheses-publication' => ['options' => $this->hypothesesPublications],
            ],
            'sort' => $this->sort,
        ];
        if ($this->advancedQuery !== null) {
            $array['adv'] = $this->advancedQuery;
        }
        if ($this->from !== null) {
            $array['facets']['date']['from'] = $this->from;
        }
        if ($this->to !== null) {
            $array['facets']['date']['to'] = $this->to;
        }
        return $array;
    }
}
