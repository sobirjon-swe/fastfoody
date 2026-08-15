<?php

namespace App\Http\Requests\Concerns;

use Closure;

/**
 * Working hours travel as "HH:MM". A client may still send "HH:MM:SS", so the
 * seconds are trimmed before validation — otherwise the same instant written
 * two ways would slip past a comparison between the two hours.
 */
trait NormalisesWorkingHours
{
    protected function normaliseWorkingHours(): void
    {
        $normalised = [];

        foreach (['opens_at', 'closes_at'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^(\d{2}:\d{2}):\d{2}$/', $value, $matches)) {
                $normalised[$field] = $matches[1];
            }
        }

        if ($normalised !== []) {
            $this->merge($normalised);
        }
    }

    /**
     * Rejects a working hour that equals its counterpart. When the client sent
     * only one of the two hours, the stored value is used for the comparison
     * without being written back into the request.
     */
    protected function differentFromWorkingHour(string $other): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($other) {
            $restaurant = $this->route('restaurant');

            $counterpart = $this->has($other)
                ? $this->input($other)
                : $restaurant?->{$other};

            if (is_string($counterpart) && $counterpart === $value) {
                $fail(__('validation.different', [
                    'attribute' => __("validation.attributes.{$attribute}"),
                    'other' => __("validation.attributes.{$other}"),
                ]));
            }
        };
    }
}
