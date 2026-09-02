<?php

declare(strict_types=1);

namespace Ract\Database\Schema;

use InvalidArgumentException;

final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    public function __construct(public readonly string $table)
    {
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        $column = $this->bigInteger($name);
        $column->primary = true;
        $column->autoIncrement = true;

        return $column;
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        $column = $this->integer($name);
        $column->primary = true;
        $column->autoIncrement = true;

        return $column;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        if ($length < 1) {
            throw new InvalidArgumentException('String column length must be positive.');
        }

        return $this->addColumn('string', $name, ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn('text', $name);
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn('integer', $name);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('big_integer', $name);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn('boolean', $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        if ($precision < 1 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException('Decimal precision and scale are invalid.');
        }

        return $this->addColumn('decimal', $name, [
            'precision' => $precision,
            'scale' => $scale,
        ]);
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn('datetime', $name);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn('timestamp', $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /** @return list<ColumnDefinition> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @param array<string, int> $parameters */
    private function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid column name "%s".', $name));
        }

        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                throw new InvalidArgumentException(sprintf('Column "%s" is already defined.', $name));
            }
        }

        $column = new ColumnDefinition($type, $name, $parameters);
        $this->columns[] = $column;

        return $column;
    }
}
