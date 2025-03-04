<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class Card
{
    private string $id;
    private string $key;
    private string $summary;
    private string $description;
    private string $status;

    public function __construct(
        string $id,
        string $key,
        string $summary,
        string $description,
        string $status
    ) {
        $this->id = $id;
        $this->key = $key;
        $this->summary = $summary;
        $this->description = $description;
        $this->status = $status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}