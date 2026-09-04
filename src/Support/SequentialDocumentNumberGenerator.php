<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Karnoweb\Commerce\Models\DocumentSequence;

/**
 * Shared sequential counter over `document_sequences`. Must be called
 * inside an existing DB transaction (checkout / invoice create already
 * wrap their work) so lockForUpdate() serializes increment.
 */
abstract class SequentialDocumentNumberGenerator
{
    abstract protected function sequenceKey(): string;

    abstract protected function format(): string;

    abstract protected function padding(): int;

    public function generate(int|string|null $branchId = null, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $normalizedBranch = $this->normalizeBranchId($branchId);
        $next = $this->increment($normalizedBranch, $year);

        return $this->render($this->format(), $year, $normalizedBranch, $next, $this->padding());
    }

    private function increment(int|string|null $branchId, int $year): int
    {
        $class = $this->sequenceModel();

        $query = $class::query()
            ->where('key', $this->sequenceKey())
            ->where('scope_year', $year);

        if ($branchId === null) {
            $query->whereNull('scope_branch_id');
        } else {
            $query->where('scope_branch_id', $branchId);
        }

        /** @var DocumentSequence|null $sequence */
        $sequence = $query->lockForUpdate()->first();

        if ($sequence === null) {
            $sequence = $class::query()->create([
                'key' => $this->sequenceKey(),
                'scope_branch_id' => $branchId,
                'scope_year' => $year,
                'current_number' => 0,
            ]);
        }

        $sequence->increment('current_number');

        return (int) $sequence->current_number;
    }

    /**
     * @return class-string<DocumentSequence>
     */
    private function sequenceModel(): string
    {
        $class = config('commerce.models.document_sequence');

        return is_string($class) && $class !== '' && class_exists($class)
            ? $class
            : DocumentSequence::class;
    }

    private function normalizeBranchId(int|string|null $branchId): int|string|null
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        return $branchId;
    }

    private function render(string $format, int $year, int|string|null $branchId, int $sequence, int $padding): string
    {
        $padded = str_pad((string) $sequence, max(1, $padding), '0', STR_PAD_LEFT);
        $branch = $branchId === null ? '' : (string) $branchId;

        $result = strtr($format, [
            '{year}' => (string) $year,
            '{branch?}' => $branch,
            '{branch}' => $branch,
            '{sequence}' => $padded,
        ]);

        $result = preg_replace('/-+/', '-', $result) ?? $result;

        return trim($result, '-');
    }
}
