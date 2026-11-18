<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservedUsernamesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every top-level SPA path that fits the username shape, from client/src/router/routes.ts.
     *
     * @return list<string>
     */
    private static function spaTopLevelNames(): array
    {
        $source = file_get_contents(base_path('../client/src/router/routes.ts'));
        preg_match_all('/\bpath:\s*"([^"]*)"/', $source, $matches);

        $names = [];
        foreach ($matches[1] as $path) {
            if ($path === '' || $path[0] === '/' || $path[0] === ':') {
                continue; // the layout roots, the empty child and the :username / catch-all routes
            }
            $segment = explode('/', $path)[0];
            if (preg_match('/^[a-z0-9_.]{3,24}$/', $segment)) {
                $names[$segment] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function spaNames(): array
    {
        $names = ['login', 'welcome', 'link', 'inbox', 'sent', 'settings', 'terms', 'privacy'];

        return array_combine($names, array_map(fn ($n) => [$n], $names));
    }

    #[DataProvider('spaNames')]
    public function test_spa_route_names_cannot_be_claimed(string $name): void
    {
        Sanctum::actingAs(User::factory()->create(['username' => null]));

        $this->patchJson('/api/me/username', ['username' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_every_username_shaped_spa_route_is_reserved(): void
    {
        $found = self::spaTopLevelNames();

        $this->assertNotEmpty($found, 'routes.ts parsing found no top-level paths');
        $this->assertSame(
            [],
            array_values(array_diff($found, config('psst.reserved_usernames'))),
            'SPA routes missing from psst.reserved_usernames'
        );
        // The provider list above must not lag behind the router either.
        $this->assertSame([], array_values(array_diff($found, array_keys(self::spaNames()))));
    }
}
