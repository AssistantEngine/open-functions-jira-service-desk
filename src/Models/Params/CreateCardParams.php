<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params;

class CreateCardParams
{
    private string $listName;
    private string $type;
    private CreateCardData $data;

    public function __construct(string $listName, string $type, CreateCardData $data)
    {
        $this->listName = $listName;
        $this->type = $type;
        $this->data = $data;
    }

    public function getListName(): string
    {
        return $this->listName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): CreateCardData
    {
        return $this->data;
    }
}