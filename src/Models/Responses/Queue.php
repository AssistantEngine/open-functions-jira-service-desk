<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class Queue
{
    public string $id;
    public string $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}