<?php

namespace Tests\Feature\Moderation;

use App\Moderation\KeywordFlagger;
use PHPUnit\Framework\TestCase;

class KeywordFlaggerTest extends TestCase
{
    public function test_matches_ignore_case_and_punctuation(): void
    {
        $this->assertSame(['kill you'], (new KeywordFlagger(['kill you']))->matches('I will KILL you!'));
    }

    public function test_matches_whole_words_only(): void
    {
        $this->assertSame([], (new KeywordFlagger(['ass']))->matches('first class'));
    }

    public function test_empty_term_list_flags_nothing(): void
    {
        $this->assertSame([], (new KeywordFlagger([]))->matches('kill you'));
        $this->assertSame([], (new KeywordFlagger(['', '  ']))->matches('anything'));
    }

    public function test_returns_each_term_once(): void
    {
        $this->assertSame(['kill you'], (new KeywordFlagger(['kill you', 'KILL YOU']))->matches('kill you, kill you'));
    }
}
