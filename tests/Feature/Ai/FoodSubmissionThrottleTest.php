<?php

use App\Models\FoodSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/FoodSubmissionSupport.php';

beforeEach(fn () => fsManifest());

/** A valid body whose source id is unique per call, so every request would create a row. */
function ftBody(int $n): array
{
    return fsBody(['source' => ['source_id' => "id-$n"]]);
}

test('the ai-submit limiter is 5 per minute and 30 per day, keyed by user', function () {
    $user = fsUser();
    $request = Request::create('/x');
    $request->setUserResolver(fn () => $user);

    $limits = RateLimiter::limiter('ai-submit')($request);

    expect(config('ai.quotas.submit'))->toBe(['per_minute' => 5, 'per_day' => 30])
        ->and(array_map(fn ($l) => [$l->maxAttempts, $l->decaySeconds], $limits))->toBe([[5, 60], [30, 86400]])
        ->and($limits[0]->key)->toContain((string) $user->id);
});

test('the sixth submission in a minute is a 429 envelope with Retry-After and creates no row', function () {
    $user = fsUser();
    foreach (range(1, 5) as $n) {
        fsSubmit($user, ftBody($n))->assertCreated();
    }

    $r = fsSubmit($user, ftBody(6));

    chatError($r, 429, 'RATE_LIMITED', true);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and(FoodSubmission::count())->toBe(5)
        ->and(FoodSubmission::query()->where('source_id', 'id-6')->exists())->toBeFalse();
});

test('the daily quota applies and quotas are per user', function () {
    config(['ai.quotas.submit.per_minute' => 100, 'ai.quotas.submit.per_day' => 2]);
    $user = fsUser();
    fsSubmit($user, ftBody(1))->assertCreated();
    fsSubmit($user, ftBody(2))->assertCreated();

    chatError(fsSubmit($user, ftBody(3)), 429, 'RATE_LIMITED', true);
    fsSubmit(fsUser(), ftBody(4))->assertCreated();
});

test('a user without submit-food gets 403 and creates no row', function () {
    $nutritionist = fsUser('nutritionist');
    foreach (range(1, 3) as $n) {
        chatError(fsSubmit($nutritionist, ftBody($n)), 403, 'FORBIDDEN');
    }
    expect(FoodSubmission::count())->toBe(0);
});
