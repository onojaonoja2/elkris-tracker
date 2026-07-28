<?php

namespace App\Models\Concerns;

trait HasSanitization
{
    public function initializeHasSanitization(): void
    {
        $this->addObservableEvents('saving');
    }

    protected function sanitizeFields(array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($this->{$field})) {
                $this->{$field} = substr(strip_tags($this->{$field}), 0, 5000);
            }
        }
    }
}
