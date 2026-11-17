<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\GdprChildren\Enums\AssuranceMethod;
use JeffersonGoncalves\GdprChildren\Facades\GdprChildren;
use JeffersonGoncalves\GdprChildren\Tests\Fixtures\Person;

beforeEach(function () {
    $this->person = Person::create(['name' => 'Emma']);
});

it('uses the member state consent age, 16 by default', function () {
    expect(GdprChildren::consentAge())->toBe(16)
        ->and(GdprChildren::consentAge('fr'))->toBe(15)
        ->and(GdprChildren::consentAge('BE'))->toBe(13)
        ->and(GdprChildren::consentAge('US'))->toBe(16);
});

it('treats unknown users as children in likely-access services', function () {
    expect(GdprChildren::age($this->person))->toBeNull()
        ->and(GdprChildren::treatAsMinor($this->person))->toBeTrue()
        ->and(GdprChildren::needsParentalConsent($this->person))->toBeTrue();

    config()->set('gdpr-children.likely_access', false);

    expect(GdprChildren::treatAsMinor($this->person))->toBeFalse()
        ->and(GdprChildren::canConsent($this->person))->toBeTrue();
});

it('needs parental consent below the consent age of the country', function () {
    GdprChildren::assure($this->person, 14, AssuranceMethod::StoreSignal);

    expect(GdprChildren::needsParentalConsent($this->person))->toBeTrue()          // 16 by default
        ->and(GdprChildren::needsParentalConsent($this->person, 'IT'))->toBeFalse() // 14 in Italy
        ->and(GdprChildren::needsParentalConsent($this->person, 'FR'))->toBeTrue(); // 15 in France
});

it('lets a verified guardian authorise consent', function () {
    $mother = Person::create(['name' => 'Anna']);
    GdprChildren::assure($this->person, 12, AssuranceMethod::Document);

    $link = GdprChildren::link($this->person, $mother, 'mother');

    expect(GdprChildren::canConsent($this->person))->toBeFalse()
        ->and(GdprChildren::missingParentalConsent()->pluck('id')->all())->toBe([$this->person->id]);

    $link->verify();

    expect(GdprChildren::canConsent($this->person))->toBeTrue()
        ->and(GdprChildren::guardians($this->person)->pluck('name')->all())->toBe(['Anna'])
        ->and(GdprChildren::missingParentalConsent())->toBeEmpty();

    $link->revoke();

    expect(GdprChildren::canConsent($this->person))->toBeFalse();
});

it('does not take self-declaration as verification and prefers reliable results', function () {
    GdprChildren::assure($this->person, 30, AssuranceMethod::SelfDeclaration);

    expect(GdprChildren::age($this->person))->toBe(30)
        ->and(GdprChildren::isVerifiedAdult($this->person))->toBeFalse()
        ->and(GdprChildren::allowsProfiling($this->person))->toBeFalse();

    GdprChildren::assure($this->person, 15, AssuranceMethod::FacialEstimation);
    GdprChildren::assure($this->person, 25, AssuranceMethod::Document);

    expect(GdprChildren::age($this->person))->toBe(15); // youngest reliable result wins
});

it('ages results and ignores expired ones', function () {
    GdprChildren::assure($this->person, 15, AssuranceMethod::Document);

    $this->travel(11)->months();
    expect(GdprChildren::age($this->person))->toBe(15);

    $this->travel(2)->months();
    expect(GdprChildren::age($this->person))->toBeNull(); // the 1-year result expired

    GdprChildren::assure($this->person, 16, AssuranceMethod::VerifiableCredential);
    expect(GdprChildren::canConsent($this->person))->toBeTrue();
});

it('rejects impossible ages', function () {
    GdprChildren::assure($this->person, -1, AssuranceMethod::Document);
})->throws(InvalidArgumentException::class);

it('gates adult routes and hides ad tags from non-adults', function () {
    Route::get('/adult', fn () => 'ok')->middleware('gdpr.adult');
    $view = '@gdprProfiling pixel @else none @endgdprProfiling';

    $this->actingAs($this->person)->get('/adult')->assertForbidden();
    expect(trim(Blade::render($view)))->toBe('none');

    GdprChildren::assure($this->person, 40, AssuranceMethod::Document);

    $this->actingAs($this->person)->get('/adult')->assertOk();
    expect(trim(Blade::render($view)))->toBe('pixel');
});

it('reports users without parental authorisation', function () {
    GdprChildren::assure($this->person, 10, AssuranceMethod::Guardian);

    $this->artisan('gdpr:children')->assertFailed();

    GdprChildren::link($this->person, Person::create(['name' => 'Dad']), 'father', verified: true);

    $this->artisan('gdpr:children')->assertSuccessful();
});
