<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params;

class UpdateCardParams
{
    private string $cardId;
    private UpdateCardData $data;

    public function __construct(string $cardId, UpdateCardData $data)
    {
        $this->cardId = $cardId;
        $this->data = $data;
    }

    public function getCardId(): string
    {
        return $this->cardId;
    }

    public function getData(): UpdateCardData
    {
        return $this->data;
    }
}