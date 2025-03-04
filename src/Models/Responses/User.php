<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class User
{
    public string $accountId;
    public string $emailAddress;
    public string $displayName;
    public bool $active;

    public function __construct(
        string $accountId,
        string $emailAddress,
        string $displayName,
        bool $active,
    ) {
        $this->accountId = $accountId;
        $this->emailAddress = $emailAddress;
        $this->displayName = $displayName;
        $this->active = $active;
    }
}