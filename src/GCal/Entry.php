<?php

namespace Calendar\GCal;

use Carbon\Carbon;
use DateTimeInterface;
use Sabre\VObject\Component\VEvent;

class Entry implements \Calendar\Entry
{

    public function __construct(private VEvent $vEvent)
    {
    }

    public function isInTimeRange(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        return $this->vEvent->isInTimeRange($start, $end);
    }

    private function getStart(): DateTimeInterface
    {
        return $this->vEvent->DTSTART->getDateTime();
    }

    private function getEnd(): DateTimeInterface
    {
        return $this->vEvent->DTEND->getDateTime();
    }

    public function getDuration(): float
    {
        return Carbon::make($this->getStart())->diffInMinutes($this->getEnd()) / 60;
    }

    public function getClient(): string
    {
        return (string)$this->vEvent->SUMMARY;
    }

    public function getProject(): string
    {
        $description = (string)$this->vEvent->DESCRIPTION;

        $description = preg_split('/\s+/', $description);

        return array_shift($description);
    }

    public function getSummary(): string
    {
        $description = (string)$this->vEvent->DESCRIPTION;

        $description = preg_split('/\s+/', $description);

        // pop off ID
        array_shift($description);

        return implode(' ', $description);
    }
}
