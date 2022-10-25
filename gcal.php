#!/usr/bin/env php
<?php

use Calendar\Entry;
use Carbon\CarbonImmutable;
use Sabre\VObject\Reader;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\StreamOutput;

require 'vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo 'Downloading feed...';
$vcalendar = Reader::read(file_get_contents($_ENV['GCAL_FEED']));
echo " DONE\n";

/** @var Entry[] $entries */
$entries = [];
foreach ($vcalendar->VEVENT as $vevent) {
    $entries[] = new Calendar\GCal\Entry($vevent);
}

// TODO: customize week number
$now = CarbonImmutable::now()->locale('nl');
echo "Getting entries for week {$now->isoWeek}\n";

$weekStart = $now->startOfWeek();
$weekEnd = $now->endOfWeek();

$clients = [];

foreach ($entries as $entry) {
    if ($entry->isInTimeRange($weekStart, $weekEnd)) {
        $clients[] = $entry->getClient();
    }
}

$dayList = [];

$clients = array_unique($clients);
foreach ($clients as $client) {
    echo "\n # {$client}\n";
    for ($d = 0; $d < 7; $d++) {
        $dayStart = $weekStart->addDays($d);
        $dayEnd = $dayStart->endOfDay();

        echo $dayStart->format('l j-n').PHP_EOL;

        $dayHours = 0;

        $projects = [];
        foreach ($entries as $entry) {
            if ($entry->getClient() !== $client) {
                continue;
            }

            if (!$entry->isInTimeRange($dayStart, $dayEnd)) {
                continue;
            }

            $desc = $entry->getProject();
            if (!isset($projects[$desc])) {
                $projects[$desc] = [];
            }
            $projects[$desc][] = $entry;
        }

        /**
         * @var Entry[] $projectEntries
         */
        foreach ($projects as $projectEntries) {
            $id = $projectEntries[0]->getProject();
            $hours = 0;
            $logs = [];
            foreach ($projectEntries as $entry) {
                $hours += $entry->getDuration();
                $logs[] = $entry->getSummary();
            }

            $log = implode(', ', array_unique($logs));
            echo "  - [{$id}] {$hours}h {$log}\n";

            $dayHours += $hours;
        }

        if (!isset($dayList[$d])) {
            $dayList[$d] = [];
        }

        $dayList[$d][$client] = $dayHours;
    }
}


$headers = [''];
$rows = [];
$total = [''];
foreach ($clients as $client) {
    $rows[$client] = [$client];
}
foreach ($dayList as $d => $hourGroups) {
    $dayStart = $weekStart->addDays($d);

    $headers[] = $dayStart->format("l\nj-n");
    foreach ($clients as $client) {
        $rows[$client][$d + 1] = '';
    }

    $t = 0;
    foreach ($hourGroups as $project => $hours) {
        $rows[$project][$d + 1] = $hours;
        $t += $hours;
    }
    $total[] = $t;
}

echo "\n\n";
$table = new Table(new StreamOutput(STDOUT));
$table->setHeaders($headers);
$table->addRows($rows);
$table->addRow(new TableSeparator());
$table->addRow($total);
$table->render();


