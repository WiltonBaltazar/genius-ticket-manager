<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// TicketTypeOversellTest needs two genuinely independent, uncommitted connections racing
// the same row — a single wrapping transaction would serialize/mask the very race this test
// exists to catch (constitution Principle III concurrency gate). Pest doesn't support binding
// a directory's test case and then excluding one file from it, so the "rest of Feature/Schema"
// is built explicitly here, excluding that one file, instead of a blanket `->in('Feature')`.
$oversellTest = __DIR__.'/Feature/Schema/TicketTypeOversellTest.php';
$otherFeatureTests = array_filter(
    glob(__DIR__.'/Feature/Schema/*.php') ?: [],
    fn (string $file): bool => $file !== $oversellTest
);

pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in(...$otherFeatureTests);

pest()->extend(TestCase::class)
    ->in($oversellTest);

pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
