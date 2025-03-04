<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params;

class CreateCardData
{
    private string $name;
    private string $desc;

    public function __construct(string $name, string $desc)
    {
        $this->name = $name;
        $this->desc = $desc;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDesc(): string
    {
        return $this->desc;
    }
}