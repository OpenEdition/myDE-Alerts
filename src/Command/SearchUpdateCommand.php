<?php

namespace MyDigitalEnvironment\AlertsBundle\Command;

use MyDigitalEnvironment\AlertsBundle\Message\SearchUpdateMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand('search:update', 'Update users search alerts')]
class SearchUpdateCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('query-cap', 'c', InputOption::VALUE_OPTIONAL, 'how many argument to ask per query', 100)
            ->addOption('id', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'search id')
            ->addOption('synchronize', null, InputOption::VALUE_NEGATABLE, 'respect search alerts frequency', true)
            ->addOption('no-limit', description: 'respect search alerts frequency')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queryCap = $input->getOption('query-cap');
        $ids = array_map(fn($d) => (int)$d, array_filter($input->getOption('id'), fn($v) => ctype_digit($v)));
        $synchronize = $input->getOption('synchronize');
        $noLimit = $input->getOption('no-limit');

        $this->bus->dispatch(new SearchUpdateMessage($ids, $synchronize, $queryCap, $noLimit));
        // todo: find way to get data out/from Envelope/disaptch

        // todo, future: helper/manager class for updating Searches Entities when we have new results ?
        return Command::SUCCESS;
    }
}
