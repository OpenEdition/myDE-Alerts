<?php

namespace MyDigitalEnvironment\AlertsBundle\Dto;

use MyDigitalEnvironment\AlertsBundle\Enum\Platform;

class SearchApiResult
{
    /** @param Document[] $documents */
    public function __construct(
        public int   $queryTime,
        public array $parameters,
        public array $pagination,
        public array $documents
    )
    {
    }

    /** format from Api Document Response  */
    public static function fromResponseData(array $data): static
    {
        return new static(
            $data['QTime'],
            $data['params'],
            $data['pagination'],
            array_map(
                fn(array $doc) => (new Document())
                    ->setType($doc['type'])
                    ->setUrl($doc['url'])
                    ->setPlatform(Platform::from($doc['platformID']))
                    ->setSite($doc['site_title'])
                    ->setAccessType($doc['via'])
                    ->setAuthors($doc['authors'])
                    ->setDate($doc['date'])
                    ->setOverview($doc['overview'])
                    ->setTitle($doc['title'])
                    ->setSubtitle($doc['subtitle'])
                ,
                $data['documents'] ?? [],
            ),
        );
    }
}