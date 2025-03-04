<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class Request
{
    public string $id;
    public string $key;
    public string $summary;
    public string $description;
    public string $status;
    public ?User $reporter;
    public ?User $assignee;
    public string $created;

    /** @var Attachment[] */
    public array $attachments = [];

    /** @var Comment[] */
    public array $comments = [];

    /** @var array[] */
    public array $transitions = [];

    /** @var array[] A list of assignable users: each array element could be ['accountId' => ..., 'displayName' => ...] */
    public array $assignableUsers = [];

    public function __construct(
        string $id,
        string $key,
        string $summary,
        string $description,
        string $status,
        ?User $reporter,
        ?User $assignee,
        string $created,
        array $attachments,
        array $comments,
        array $transitions,
        array $assignableUsers
    ) {
        $this->id = $id;
        $this->key = $key;
        $this->summary = $summary;
        $this->description = $description;
        $this->status = $status;
        $this->reporter = $reporter;
        $this->assignee = $assignee;
        $this->created = $created;
        $this->attachments = $attachments;
        $this->comments = $comments;
        $this->transitions = $transitions;
        $this->assignableUsers = $assignableUsers;
    }
}