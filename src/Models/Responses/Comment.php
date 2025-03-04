<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class Comment
{
    public string $id;
    public User $author;
    public User $updateAuthor;
    public string $bodyText;     // Extracted text from the comment body doc
    public array $attachments;   // Attachments extracted from the comment (if any)
    public string $created;
    public string $updated;
    public bool $isPublic;

    /**
     * @param Attachment[] $attachments
     */
    public function __construct(
        string $id,
        User $author,
        User $updateAuthor,
        string $bodyText,
        array $attachments,
        string $created,
        string $updated,
        bool $isPublic
    ) {
        $this->id = $id;
        $this->author = $author;
        $this->updateAuthor = $updateAuthor;
        $this->bodyText = $bodyText;
        $this->attachments = $attachments;
        $this->created = $created;
        $this->updated = $updated;
        $this->isPublic = $isPublic;
    }
}