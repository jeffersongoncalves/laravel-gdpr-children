<?php

namespace JeffersonGoncalves\GdprChildren\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\GdprChildren\ChildrenManager;

class ChildrenCommand extends Command
{
    public $signature = 'gdpr:children {--json : Print the report as JSON}';

    public $description = 'List users below the age of digital consent without a verified guardian (GDPR Art. 8)';

    public function handle(ChildrenManager $children): int
    {
        $missing = $children->missingParentalConsent()
            ->map(fn (Model $subject): array => ['type' => $subject->getMorphClass(), 'id' => $subject->getKey(), 'age' => $children->age($subject)])
            ->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($missing, JSON_PRETTY_PRINT));
        } else {
            $this->components->info(count($missing).' user(s) below the age of digital consent ('.$children->consentAge().') without parental authorisation.');
            $this->table(['Type', 'ID', 'Age'], $missing);
        }

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }
}
