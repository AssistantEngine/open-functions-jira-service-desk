<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses;

class Attachment
{
    public string $id;
    public string $filename;
    public ?User $author;
    public string $created;
    public int $size;
    public string $mimeType;
    public string $contentUrl;
    public string $thumbnailUrl;
    public ?string $content; // to store the binary content

    public function __construct(
        string $id,
        string $filename,
        ?User $author,
        string $created,
        int $size,
        string $mimeType,
        string $contentUrl,
        string $thumbnailUrl
    ) {
        $this->id = $id;
        $this->filename = $filename;
        $this->author = $author;
        $this->created = $created;
        $this->size = $size;
        $this->mimeType = $mimeType;
        $this->contentUrl = $contentUrl;
        $this->thumbnailUrl = $thumbnailUrl;
        $this->content = null; // Initialize to null until fetched
    }
}