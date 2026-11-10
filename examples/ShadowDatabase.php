<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Examples;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use SolveBeam\Twinfield\BrowseData\BrowseResult;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Twinfield;
use UnexpectedValueException;

/**
 * A small shadow database of transaction lines in SQLite, for the shadow-database examples.
 *
 *   $database = ShadowDatabase::open($twinfield);
 *   $database->replaceTransactions($result);
 *
 * One file in the temp directory holds the lines of every company you sync, keyed by the
 * organisation id and the company code (see "Keys for a shadow database" in the organisations
 * guide). In your own application you use your own database; the queries to Twinfield stay the
 * same.
 */
final class ShadowDatabase
{
    private function __construct(
        private readonly PDO $pdo,
        private readonly CompanyReference $company,
        public readonly string $file,
    ) {
    }

    public static function open(Twinfield $twinfield): self
    {
        $file = sys_get_temp_dir() . '/twinfield-shadow-database.sqlite';
        $pdo = new PDO('sqlite:' . $file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS transaction_lines (
                organisation TEXT NOT NULL,
                company TEXT NOT NULL,
                day_book TEXT NOT NULL,
                number INTEGER NOT NULL,
                line INTEGER NOT NULL,
                year INTEGER NOT NULL,
                period INTEGER NOT NULL,
                date TEXT NOT NULL,
                status TEXT NOT NULL,
                currency TEXT NOT NULL,
                account TEXT NOT NULL,
                dimension2 TEXT,
                dimension3 TEXT,
                value TEXT NOT NULL,
                base_value TEXT NOT NULL,
                description TEXT,
                modified TEXT NOT NULL,
                PRIMARY KEY (organisation, company, day_book, number, line)
            )
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sync (
                organisation TEXT NOT NULL,
                company TEXT NOT NULL,
                synced_until TEXT NOT NULL,
                PRIMARY KEY (organisation, company)
            )
            SQL);

        return new self($pdo, $twinfield->companyReference(), $file);
    }

    /**
     * Replaces all lines of the transactions in the result: a changed transaction can have more
     * or fewer lines than before, so its old lines are deleted first.
     *
     * The result needs the columns of the examples (see initial-load).
     *
     * @return int The number of transactions.
     */
    public function replaceTransactions(BrowseResult $result): int
    {
        $delete = $this->pdo->prepare(<<<'SQL'
            DELETE FROM transaction_lines
            WHERE organisation = ? AND company = ? AND day_book = ? AND number = ?
            SQL);

        $insert = $this->pdo->prepare(<<<'SQL'
            INSERT INTO transaction_lines (
                organisation, company, day_book, number, line, year, period, date, status, currency,
                account, dimension2, dimension3, value, base_value, description, modified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            SQL);

        $replaced = [];

        $this->pdo->beginTransaction();

        foreach ($result as $row) {
            $line = $row->transactionLine
                ?? throw new UnexpectedValueException('A row without transaction line: use a browse code with lines.');

            $transaction = $line->dayBook . ':' . $line->number;

            if (!isset($replaced[$transaction])) {
                $delete->execute([...$this->key(), $line->dayBook, $line->number]);
                $replaced[$transaction] = true;
            }

            $insert->execute([
                ...$this->key(),
                $line->dayBook,
                $line->number,
                $line->line,
                $row->int('fin.trs.head.year'),
                $row->int('fin.trs.head.period'),
                $row->date('fin.trs.head.date')->format('Y-m-d'),
                $row->string('fin.trs.head.status'),
                $row->string('fin.trs.head.curcode'),
                $row->string('fin.trs.line.dim1'),
                $row->nullableString('fin.trs.line.dim2'),
                $row->nullableString('fin.trs.line.dim3'),
                (string) $row->amount('fin.trs.line.valuesigned'),
                (string) $row->amount('fin.trs.line.basevaluesigned'),
                $row->nullableString('fin.trs.line.description'),
                $row->dateTime('fin.trs.head.modified')->format('Y-m-d H:i:s'),
            ]);
        }

        $this->pdo->commit();

        return count($replaced);
    }

    /**
     * Deletes all lines of a transaction.
     *
     * @return int The number of lines deleted: 0 when the transaction was not in the database.
     */
    public function deleteTransaction(string $dayBook, int $number): int
    {
        $delete = $this->pdo->prepare(<<<'SQL'
            DELETE FROM transaction_lines
            WHERE organisation = ? AND company = ? AND day_book = ? AND number = ?
            SQL);

        $delete->execute([...$this->key(), $dayBook, $number]);

        return $delete->rowCount();
    }

    /**
     * The moment (UTC) up to which the company was synced, or null before the initial load.
     */
    public function syncedUntil(): ?DateTimeImmutable
    {
        $select = $this->pdo->prepare('SELECT synced_until FROM sync WHERE organisation = ? AND company = ?');
        $select->execute($this->key());

        $value = $select->fetchColumn();

        return is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
    }

    public function markSynced(DateTimeImmutable $until): void
    {
        $upsert = $this->pdo->prepare(<<<'SQL'
            INSERT INTO sync (organisation, company, synced_until) VALUES (?, ?, ?)
            ON CONFLICT (organisation, company) DO UPDATE SET synced_until = excluded.synced_until
            SQL);

        $upsert->execute([...$this->key(), $until->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
    }

    /**
     * The balance per general ledger account of a year, in the base currency, summed with Amount:
     * SQL would sum the amounts as floats.
     *
     * @return array<string, Amount> By account code, sorted.
     */
    public function balances(int $year): array
    {
        $select = $this->pdo->prepare(<<<'SQL'
            SELECT account, base_value
            FROM transaction_lines
            WHERE organisation = ? AND company = ? AND year = ?
            SQL);

        $select->execute([...$this->key(), $year]);

        $balances = [];

        /** @var list<array{account: string, base_value: string}> $lines */
        $lines = $select->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lines as $line) {
            $balance = Amount::fromString($line['base_value']);
            $balances[$line['account']] = ($balances[$line['account']] ?? Amount::zero())->plus($balance);
        }

        ksort($balances, SORT_STRING);

        return $balances;
    }

    public function countLines(): int
    {
        $select = $this->pdo->prepare('SELECT COUNT(*) FROM transaction_lines WHERE organisation = ? AND company = ?');
        $select->execute($this->key());

        return (int) $select->fetchColumn();
    }

    /**
     * @return array{string, string}
     */
    private function key(): array
    {
        return [$this->company->organisation->id, $this->company->code];
    }
}
