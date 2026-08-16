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

// TicketTypeOversellTest and OrderSubmissionOversellTest each need two genuinely
// independent, uncommitted connections racing the same row — a single wrapping
// transaction would serialize/mask the very race these tests exist to catch
// (constitution Principle III concurrency gate). Pest doesn't support binding
// a directory's test case and then excluding some files from it, so the "rest of
// Feature" (Schema/ from feature 001, Auth/ from feature 002, Filament/ from
// feature 003, Checkout/ from feature 004, Admin/ from the door check-in page)
// is built explicitly here, excluding those two files, instead of a blanket
// `->in('Feature')`.
$noTransactionTests = [
    __DIR__.'/Feature/Schema/TicketTypeOversellTest.php',
    __DIR__.'/Feature/Checkout/OrderSubmissionOversellTest.php',
];
$otherFeatureTests = array_filter(
    array_merge(
        glob(__DIR__.'/Feature/Schema/*.php') ?: [],
        glob(__DIR__.'/Feature/Auth/*.php') ?: [],
        glob(__DIR__.'/Feature/Filament/*.php') ?: [],
        glob(__DIR__.'/Feature/Checkout/*.php') ?: [],
        glob(__DIR__.'/Feature/Admin/*.php') ?: [],
    ),
    fn (string $file): bool => ! in_array($file, $noTransactionTests, true)
);

pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in(...$otherFeatureTests);

pest()->extend(TestCase::class)
    ->in(...array_filter($noTransactionTests, 'file_exists'));

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
