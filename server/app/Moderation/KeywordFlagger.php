<?php

namespace App\Moderation;

class KeywordFlagger
{
    /** @var list<string> */
    private array $terms;

    /** @param  array<int, mixed>  $terms */
    public function __construct(array $terms)
    {
        $clean = [];
        foreach ($terms as $term) {
            $term = mb_strtolower(trim((string) $term));
            if ($term !== '') {
                $clean[$term] = true;
            }
        }
        $this->terms = array_map('strval', array_keys($clean));
    }

    /** @return list<string> matched terms, lowercased, each once */
    public function matches(string $body): array
    {
        $found = [];

        foreach ($this->terms as $term) {
            $words = array_map(fn (string $w) => preg_quote($w, '/'), preg_split('/\s+/u', $term) ?: [$term]);
            $pattern = '/(?<![\p{L}\p{N}])'.implode('[^\p{L}\p{N}]+', $words).'(?![\p{L}\p{N}])/iu';

            if (preg_match($pattern, $body) === 1) {
                $found[] = $term;
            }
        }

        return $found;
    }
}
