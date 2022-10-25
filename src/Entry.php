<?php

namespace Calendar;

interface Entry
{
    public function isInTimeRange(\DateTime $start, \DateTime $end): bool;

    public function getDuration(): float;

    public function getClient(): string;

    public function getProject(): string;

    public function getSummary(): string;
}
