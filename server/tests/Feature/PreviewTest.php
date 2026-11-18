<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    use RefreshDatabase;

    private function alice(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'username' => 'alice',
            'name' => 'Alice Wonderland',
            'email' => 'alice.private@example.test',
            'avatar_url' => 'https://lh3.googleusercontent.com/a/secret-avatar',
        ], $overrides));
    }

    public function test_preview_has_og_tags_and_username_only(): void
    {
        $this->alice();

        $response = $this->get('/preview/alice')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="Message @alice anonymously · Psst">', $html);
        $this->assertStringContainsString('<meta property="og:description"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.config('app.url').'/og.png">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.config('app.url').'/alice">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta name="twitter:title"', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.config('app.url').'/og.png">', $html);

        foreach (['Wonderland', 'alice.private', 'example.test', 'secret-avatar', 'googleusercontent'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_preview_is_noindex(): void
    {
        $this->alice();

        $response = $this->get('/preview/alice')->assertOk();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $response->getContent());
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    public function test_preview_redirects_real_browsers_to_the_app_path(): void
    {
        $this->alice();

        $html = $this->get('/preview/alice')->getContent();

        $this->assertStringContainsString('<meta http-equiv="refresh" content="0;url=/alice">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.config('app.url').'/alice">', $html);
    }

    public function test_preview_404_for_unknown_and_banned(): void
    {
        $this->alice(['banned_at' => now()]);

        foreach (['/preview/alice', '/preview/nobody'] as $path) {
            $response = $this->get($path)->assertNotFound();
            $this->assertStringNotContainsString('og:title', $response->getContent());
            $this->assertStringNotContainsString('alice', strtolower($response->getContent()));
        }
    }

    public function test_preview_rejects_markup_in_username(): void
    {
        $this->get('/preview/'.rawurlencode('<script>alert(1)</script>'))->assertNotFound();
        $this->get('/preview/'.rawurlencode('"><img src=x>'))->assertNotFound();
        $this->get('/preview/Alice')->assertNotFound();
        $this->get('/preview/ab')->assertNotFound();
        $this->get('/preview/'.str_repeat('a', 25))->assertNotFound();
    }

    public function test_preview_never_serves_reserved_usernames(): void
    {
        foreach (config('psst.reserved_usernames') as $reserved) {
            // Legacy or seeded rows must still not produce a preview. The DB format check rejects the short or hyphenated ones.
            if (preg_match('/^[a-z0-9_.]{3,24}$/', $reserved)) {
                User::factory()->create(['username' => $reserved]);
            }
            $this->get('/preview/'.$reserved)->assertNotFound();
        }
    }
}
