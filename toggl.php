#!/usr/bin/env php
<?php

use Carbon\Carbon;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\StreamOutput;

require 'vendor/autoload.php';

class TogglClient
{
    public string $id;
    public string $wid;
    public string $name;
    public string $at;
}

/**
 * @return TogglClient[]
 */
function getClients(Client $client, int $workspace_id, string $api_token): mixed
{
    $res = $client->get('https://api.track.toggl.com/api/v9/workspaces/'.$workspace_id.'/clients', [
        'auth' => [$api_token, 'api_token'],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    return json_decode($res->getBody()->getContents());
}

function getProjects(Client $client, int $workspace_id, string $api_token): mixed
{
    $res = $client->get('https://api.track.toggl.com/api/v9/workspaces/'.$workspace_id.'/projects', [
        'auth' => [$api_token, 'api_token'],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    return json_decode($res->getBody()->getContents());
}

function getEntries(Client $client, string $weekStartDate, string $weekEndDate, string $api_token): mixed
{
    $res = $client->get('https://api.track.toggl.com/api/v9/me/time_entries', [
        'query' => [
            'start_date' => $weekStartDate,
            'end_date' => $weekEndDate,
        ],
        'auth' => [$api_token, 'api_token'],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    return json_decode($res->getBody()->getContents());
}

$now = Carbon::now('Europe/Amsterdam');
$offset = null;
switch ($argc) {
    case 1:
        $offset = 0;
        break;
    case 2:
        $offset = intval($argv[1], 10);
        break;
    default:
        error_log("Usage: ${$argv[0]} [weekOffset]");
        exit(1);
}

$now->addWeeks($offset);

$weekStartDate = $now->startOfWeek()->format('Y-m-d');
$weekEndDate = $now->endOfWeek()->format('Y-m-d');

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$api_token = $_ENV['TOGGL_API_TOKEN'];
$workspace_id = $_ENV['TOGGL_WORKSPACE'] ?? null;

$client = new Client();

$entries = getEntries($client, $weekStartDate, $weekEndDate, $api_token);
if (!$workspace_id) {
    foreach ($entries as $entry) {
        $workspace_id = $entry->workspace_id;
        break;
    }
}
$clients = getClients($client, $workspace_id, $api_token);
$projects = getProjects($client, $workspace_id, $api_token);


$headers = [''];
for ($weekday = 0; $weekday < 7; $weekday++) {
    $day = $now->startOfWeek()->addDays($weekday);
    $headers[] = $day->format("l\nj-n");
}

$parsedEntries = [];
$rows = [];
foreach ($clients as $client) {
    $row = [];
    $row[] = $client->name;

    echo sprintf("# %s\n", $client->name);

    for ($weekday = 0; $weekday < 7; $weekday++) {
        $day = $now->startOfWeek()->addDays($weekday);
        echo $day->format('l j-n').PHP_EOL;

        foreach ($projects as $project) {
            if ($project->client_id != $client->id) {
                continue;
            }

            $workHours = 0;
            $work = [];
            foreach ($entries as $entry) {
                if ($entry->project_id !== $project->id) {
                    continue;
                }
                if ($day->isSameDay(Carbon::parse($entry->start))) {
                    $workHours += $entry->duration;
                    $work[] = $entry->description;
                }
            }

            if ($workHours !== 0) {
                $workHours = $workHours / (60 * 60);
                $work = array_unique($work);
                $log = implode(', ', $work);
                $array = preg_split("/\s+/", $project->name);
                $id = array_shift($array);
                echo "  - [{$id}] {$workHours}h {$log}\n";
            }
        }

        $hours = 0;
        foreach ($entries as $entry) {
            $project = null;
            foreach ($projects as $p) {
                if ($p->id == $entry->project_id) {
                    $project = $p;
                    break;
                }
            }

            if (!$project) {
                continue;
            }

            if ($project->client_id != $client->id) {
                continue;
            }

            if ($day->isSameDay(Carbon::parse($entry->start))) {
                $hours += $entry->duration;
                $parsedEntries[] = $entry->id;
            }
        }

        $row[] = $hours / (60 * 60);
    }

    $rows[] = $row;
    echo "\n";
}

{
    $otherRow = [];
    $otherRow[] = 'Other';

    for ($weekday = 0; $weekday < 7; $weekday++) {
        $day = $now->startOfWeek()->addDays($weekday);

        $hours = 0;
        foreach ($entries as $entry) {
            if (in_array($entry->id, $parsedEntries)) {
                continue;
            }

            if ($day->isSameDay(Carbon::parse($entry->start))) {
                error_log('ERROR: No project set on '.$entry->description);
                $hours += $entry->duration;
            }
        }

        $otherRow[] = $hours / (60 * 60);
    }
}

{
    $total = [];
    $total[] = '';

    for ($weekday = 0; $weekday < 7; $weekday++) {
        $day = $now->startOfWeek()->addDays($weekday);

        $hours = 0;
        foreach ($entries as $entry) {
            if ($day->isSameDay(Carbon::parse($entry->start))) {
                $hours += $entry->duration;
            }
        }

        $total[] = $hours / (60 * 60);
    }
}


$table = new Table(new StreamOutput(STDOUT));
$table->setHeaders($headers);
$table->addRows($rows);
$table->addRow(new TableSeparator());
$table->addRow($otherRow);
$table->addRow(new TableSeparator());
$table->addRow($total);
$table->render();
