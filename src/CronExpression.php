<?php

declare(strict_types=1);

namespace Grandpa;

class CronExpression
{
    public function __construct(
        private readonly string $expression,
    ) {
    }

    public function isDue(\DateTimeInterface $time): bool
    {
        $fields = preg_split('/\s+/', trim($this->expression));

        if (count($fields) === 6) {
            [$second, $minute, $hour, $day, $month, $weekday] = $fields;

            if (!$this->matches($second, (int) $time->format('s'))) {
                return false;
            }
        } elseif (count($fields) === 5) {
            [$minute, $hour, $day, $month, $weekday] = $fields;
        } else {
            throw new \InvalidArgumentException("Invalid cron expression \"{$this->expression}\".");
        }

        return $this->matches($minute, (int) $time->format('i'))
            && $this->matches($hour, (int) $time->format('G'))
            && $this->matches($day, (int) $time->format('j'))
            && $this->matches($month, (int) $time->format('n'))
            && $this->matches($weekday, (int) $time->format('w'));
    }

    private function matches(string $field, int $value): bool
    {
        foreach (explode(',', $field) as $part) {
            if ($this->partMatches($part, $value)) {
                return true;
            }
        }

        return false;
    }

    private function partMatches(string $part, int $value): bool
    {
        $step = 1;

        if (str_contains($part, '/')) {
            [$part, $stepPart] = explode('/', $part, 2);
            $step = (int) $stepPart;
        }

        if ($part === '*') {
            return $value % $step === 0;
        }

        if (str_contains($part, '-')) {
            [$start, $end] = array_map('intval', explode('-', $part, 2));

            return $value >= $start && $value <= $end && ($value - $start) % $step === 0;
        }

        return $value === (int) $part;
    }
}
