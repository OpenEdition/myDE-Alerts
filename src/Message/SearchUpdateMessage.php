<?php

namespace MyDigitalEnvironment\AlertsBundle\Message;

final class SearchUpdateMessage
{

    /**
     * @param int[] $ids
     */
    public function __construct(
        public array $ids = [],
        public bool $synchronize = true,
        public int $queryCap = 100,
        public bool $noLimit = false,
    ) {
        $this->ids = array_filter($ids, fn(mixed $v) => is_int($v));
    }


}
