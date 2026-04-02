<?php

namespace App\Dto\ClassRep;

/**
 * Single student row for class-rep roster API (no sensitive fields).
 */
final readonly class ClassRepStudentRow
{
    public function __construct(
        public int $id,
        public string $indexNumber,
        public string $name,
        public ?int $classId,
        public ?string $className,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'index_number' => $this->indexNumber,
            'name' => $this->name,
            'class_id' => $this->classId,
            'class_name' => $this->className,
        ];
    }
}
