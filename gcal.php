#!/usr/bin/env php
<?php

use Carbon\CarbonImmutable;
use Sabre\VObject\Component\VEvent;
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

// TODO: customize week number
$now = CarbonImmutable::now()->locale('nl');
echo "Getting events for week {$now->isoWeek}\n";

$weekStart = $now->startOfWeek();
$weekEnd = $now->endOfWeek();

$clients = [];

foreach ($vcalendar->VEVENT as $event) {
    if ($event->isInTimeRange($weekStart, $weekEnd)) {
        $clients[] = (string)$event->SUMMARY;
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

        $tasks = [];

        /** @var VEvent $event */
        foreach ($vcalendar->VEVENT as $event) {
            if ((string)$event->SUMMARY !== $client) {
                continue;
            }

            if (!$event->isInTimeRange($dayStart, $dayEnd)) {
                continue;
            }

            $dtStart = CarbonImmutable::createFromInterface($event->DTSTART->getDateTime());
            $dtEnd = CarbonImmutable::createFromInterface($event->DTEND->getDateTime());
            $desc = (string)$event->DESCRIPTION;
            if (!isset($tasks[$desc])) {
                $tasks[$desc] = 0;
            }
            $tasks[$desc] += $dtStart->diffInMinutes($dtEnd) / 60;
        }

        foreach ($tasks as $task => $hours) {
            if (empty($task)) {
                $task = 'null';
            }

            $task = preg_split('/\s+/', $task);

            $id = array_shift($task);
            $log = implode(' ', $task);
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


