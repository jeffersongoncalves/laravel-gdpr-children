<?php

namespace JeffersonGoncalves\GdprChildren;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JeffersonGoncalves\GdprChildren\Enums\AssuranceMethod;
use JeffersonGoncalves\GdprChildren\Models\AgeAssurance;
use JeffersonGoncalves\GdprChildren\Models\GuardianLink;

class ChildrenManager
{
    /**
     * Record the result of an age check. Pass the age, never the document.
     */
    public function assure(Model $subject, int $age, AssuranceMethod $method, ?string $provider = null): AgeAssurance
    {
        if ($age < 0 || $age > 130) {
            throw new InvalidArgumentException("Invalid age [{$age}].");
        }

        $validFor = config('gdpr-children.valid_for.'.$method->value);

        return AgeAssurance::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'age' => $age,
            'method' => $method,
            'provider' => $provider,
            'assured_at' => now(),
            'expires_at' => $validFor === null ? null : now()->add(CarbonInterval::make((string) $validFor)),
        ]);
    }

    /**
     * Current age, or null when unknown. Reliable results outweigh self-declaration; among the
     * results that count, the youngest wins (the most protective reading).
     */
    public function age(?Model $subject): ?int
    {
        return $this->decide($subject)['age'];
    }

    public function isVerifiedAdult(?Model $subject): bool
    {
        $decision = $this->decide($subject);

        return $decision['reliable'] && $decision['age'] !== null && $decision['age'] >= 18;
    }

    /** A known minor, or nobody knows and the service is likely used by children. */
    public function treatAsMinor(?Model $subject): bool
    {
        $age = $this->age($subject);

        return $age === null ? (bool) config('gdpr-children.likely_access', true) : $age < 18;
    }

    /** Age of digital consent for a member state (Art. 8(1)), or the default. */
    public function consentAge(?string $country = null): int
    {
        $ages = (array) config('gdpr-children.consent_ages', []);

        return (int) ($country !== null && isset($ages[strtoupper($country)])
            ? $ages[strtoupper($country)]
            : config('gdpr-children.consent_age', 16));
    }

    /** Below the age of digital consent (unknown counts as below when likely_access). */
    public function belowConsentAge(?Model $subject, ?string $country = null): bool
    {
        $age = $this->age($subject);

        return $age === null ? (bool) config('gdpr-children.likely_access', true) : $age < $this->consentAge($country);
    }

    /**
     * Consent given by this user alone is not valid: it must be given or authorised by the
     * holder of parental responsibility (Art. 8(1)), here a verified guardian link.
     */
    public function needsParentalConsent(?Model $subject, ?string $country = null): bool
    {
        return $subject !== null && $this->belowConsentAge($subject, $country) && ! $this->hasGuardian($subject);
    }

    public function canConsent(?Model $subject, ?string $country = null): bool
    {
        return $subject !== null && ! $this->needsParentalConsent($subject, $country);
    }

    /** No advertising based on profiling for minors (DSA Art. 28(2)): only verified adults. */
    public function allowsProfiling(?Model $subject): bool
    {
        return $this->isVerifiedAdult($subject);
    }

    public function hasGuardian(Model $minor): bool
    {
        return $this->links($minor)->scopes('active')->exists();
    }

    /**
     * Link a child to the holder of parental responsibility. Verify it with reasonable
     * efforts, taking available technology into account (Art. 8(2)).
     */
    public function link(Model $minor, Model $guardian, ?string $relationship = null, bool $verified = false): GuardianLink
    {
        return GuardianLink::create([
            'minor_type' => $minor->getMorphClass(),
            'minor_id' => $minor->getKey(),
            'guardian_type' => $guardian->getMorphClass(),
            'guardian_id' => $guardian->getKey(),
            'relationship' => $relationship,
            'verified_at' => $verified ? now() : null,
        ]);
    }

    /**
     * @return Collection<int, Model>
     */
    public function guardians(Model $minor): Collection
    {
        return $this->links($minor)->scopes('active')->with('guardian')->get()
            ->map(fn (GuardianLink $link): ?Model => $link->guardian)
            ->filter()
            ->values();
    }

    /**
     * Users with a valid age result below the default consent age and no verified guardian.
     *
     * @return Collection<int, Model>
     */
    public function missingParentalConsent(): Collection
    {
        // ponytail: loads every subject with a valid result; paginate by subject if this grows large
        return AgeAssurance::query()->scopes('valid')->with('subject')->get()
            ->map(fn (AgeAssurance $assurance): ?Model => $assurance->subject)
            ->filter()
            ->unique(fn (Model $subject): string => $subject->getMorphClass().'#'.$subject->getKey())
            ->filter(fn (Model $subject): bool => $this->needsParentalConsent($subject))
            ->values();
    }

    /**
     * @return array{age: int|null, reliable: bool}
     */
    protected function decide(?Model $subject): array
    {
        if ($subject === null) {
            return ['age' => null, 'reliable' => false];
        }

        $valid = AgeAssurance::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->scopes('valid')
            ->get();

        $reliable = $valid->filter(fn (AgeAssurance $a): bool => $a->method->isReliable());
        $counted = $reliable->isNotEmpty() ? $reliable : $valid;
        $age = $counted->map(fn (AgeAssurance $a): int => $a->currentAge())->min();

        return ['age' => $age === null ? null : (int) $age, 'reliable' => $reliable->isNotEmpty()];
    }

    /**
     * @return Builder<GuardianLink>
     */
    protected function links(Model $minor): Builder
    {
        return GuardianLink::query()
            ->where('minor_type', $minor->getMorphClass())
            ->where('minor_id', $minor->getKey());
    }
}
