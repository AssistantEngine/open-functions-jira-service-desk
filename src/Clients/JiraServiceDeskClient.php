<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk\Clients;

use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\CreateCardParams;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\UpdateCardParams;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses\Attachment;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses\Comment;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses\Queue;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses\Request;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Responses\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class JiraServiceDeskClient
{
    private Client $client;
    private string $apiBasePath = '/rest/api/3/';
    private string $serviceDeskBasePath = '/rest/servicedeskapi/';
    private string $serviceDeskId;

    public function __construct(string $baseUri, string $email, string $apiToken, string $serviceDeskId)
    {
        $this->serviceDeskId = $serviceDeskId;
        $this->client = new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'auth' => [$email, $apiToken],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-ExperimentalApi' => 'opt-in',
            ],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->get("servicedesk/{$this->serviceDeskId}/queue", [], 'servicedesk');

        if (!isset($response['values']) || !is_array($response['values'])) {
            return [];
        }

        $queues = [];
        foreach ($response['values'] as $q) {
            $queues[] = new Queue(
                (string)($q['id'] ?? ''),
                $q['name'] ?? ''
            );
        }

        return $queues;
    }

    public function showCards(string $listId): array
    {
        $response = $this->get("servicedesk/{$this->serviceDeskId}/queue/{$listId}/issue", [], 'servicedesk');

        if (!isset($response['values']) || !is_array($response['values'])) {
            return [];
        }

        $requests = [];
        foreach ($response['values'] as $issueData) {
            $requests[] = $this->mapIssueToRequest($issueData);
        }

        return $requests;
    }

    public function getCard(string $cardId): ?Request
    {
        $response = $this->get("issue/{$cardId}");

        if (isset($response['id'])) {
            return $this->mapIssueToRequest($response);
        }
        return null;
    }

    public function createCard(CreateCardParams $params)
    {
        $serviceDeskId = $this->serviceDeskId ?? null;
        if (!$serviceDeskId) {
            return ['error' => 'serviceDeskId is required for creating requests.'];
        }

        // If the serviceDeskId is not numeric (e.g. a project key like "SUP"), map it to its corresponding id.
        if (!ctype_digit($serviceDeskId)) {
            $mappedServiceDeskId = $this->mapServiceDeskKeyToId($serviceDeskId);
            if (!$mappedServiceDeskId) {
                return ['error' => "Unable to map service desk key '{$serviceDeskId}' to a valid service desk id."];
            }
            $serviceDeskId = $mappedServiceDeskId;
        }

        $requestTypeId = $this->getRequestTypeIdByName($serviceDeskId, $params->getType());
        if (!$requestTypeId) {
            return ['error' => "Request type '{$params->getType()}' not found."];
        }

        // Convert the description from Markdown to doc format
        $descDoc = $this->markdownToDoc($params->getData()->getDesc());

        $body = [
            'serviceDeskId' => $serviceDeskId,
            'requestTypeId' => $requestTypeId,
            'isAdfRequest' => true,
            'requestFieldValues' => [
                'summary'     => $params->getData()->getName(),
                'description' => $descDoc
            ]
        ];

        $response = $this->post('request', $body, 'servicedesk');

        return $response['issueId'] ?? false;
    }

    /**
     * Maps a service desk key (e.g. "SUP") to its corresponding numeric service desk id.
     *
     * @param string $serviceDeskKey The service desk key provided.
     * @return string|null The numeric service desk id if found; otherwise, null.
     */
    private function mapServiceDeskKeyToId(string $serviceDeskKey): ?string
    {
        // Call the Jira Service Desk API endpoint that returns a list of service desks.
        $response = $this->get("servicedesk", [], 'servicedesk');

        if (!isset($response['values']) || !is_array($response['values'])) {
            return null;
        }

        // Look for a service desk where the project key matches the provided key.
        foreach ($response['values'] as $serviceDesk) {
            if (strcasecmp($serviceDesk['projectKey'] ?? '', $serviceDeskKey) === 0) {
                return $serviceDesk['id'];
            }
        }
        return null;
    }

    public function updateCard(UpdateCardParams $params)
    {
        // Convert the description from Markdown to doc format
        $descDoc = $this->markdownToDoc($params->getData()->getDesc());

        $data = [
            'summary' => $params->getData()->getName(),
            'description' => $descDoc
        ];

        $this->put("issue/{$params->getCardId()}", ['fields' => $data]);
        return ['success' => true];
    }

    public function createComment(string $cardId, string $text, bool $isPublic): array
    {
        // Convert the comment text from Markdown to doc format
        $commentDoc = $this->markdownToDoc($text);

        $body = [
            'body' => $commentDoc,
            "properties" => [
                [
                    "key" => "sd.public.comment",
                    "value" => [
                        "internal"=> !$isPublic
                    ]
                ]
            ]
        ];

        $response = $this->post("issue/{$cardId}/comment", $body);
        return [
            "comment_id" => $response['id'] ?? null,
        ];
    }

    public function changeStatus(string $cardId, string $transitionId): array
    {
        $body = [
            'transition' => [
                'id' => $transitionId
            ]
        ];

        $response = $this->post("issue/{$cardId}/transitions", $body, 'api');

        if (isset($response['error'])) {
            $transitionOptions = $this->getTransitionsForIssue($cardId);
            return ['error' => 'Only the following transitions are allowed.', "options" => $transitionOptions];
        }

        return ['success' => true, 'newStatus' => $transitionId];
    }

    public function changePriority(string $cardId, string $priorityName): array
    {
        $body = [
            'fields' => [
                'priority' => [
                    'name' => $priorityName
                ]
            ]
        ];

        $response = $this->put("issue/{$cardId}", $body, 'api');
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        return ['success' => true, 'newPriority' => $priorityName];
    }

    public function assignUser(string $cardId, string $displayName): array
    {
        $users = $this->getAssignableUsersForIssue($cardId);

        $accountId = null;
        foreach ($users as $u) {
            if (isset($u['displayName']) && $u['displayName'] === $displayName) {
                $accountId = $u['accountId'];
                break;
            }
        }

        if (!$accountId) {
            return ['error' => "No user found with name '{$displayName}'"];
        }

        $body = [
            'fields' => [
                'assignee' => [
                    'accountId' => $accountId
                ]
            ]
        ];

        $response = $this->put("issue/{$cardId}", $body, 'api');

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        return ['success' => true, 'assignee' => $displayName];
    }

    public function getAssignableUsersForIssue(string $cardId): array
    {
        $response = $this->get("user/assignable/search", ['issueId' => $cardId], 'api');
        if (is_array($response)) {
            return $response;
        }
        return [];
    }

    public function getTransitionsForIssue(string $cardId): array
    {
        $response = $this->get("issue/{$cardId}/transitions", [], 'api');
        if (isset($response['transitions']) && is_array($response['transitions'])) {
            return $response['transitions'];
        }
        return [];
    }

    public function getTypes(): array
    {
        return $this->getRequestTypes();
    }

    public function getPriorities(): array
    {
        $response = $this->get("priority", [], 'api');

        if (isset($response['error'])) {
            return [];
        }

        if (!is_array($response)) {
            return [];
        }

        return $response;
    }

    private function mapIssueToRequest(array $issueData): Request
    {
        $fields = $issueData['fields'] ?? [];

        $summary = $fields['summary'] ?? '';
        $description = $this->extractDescriptionText($fields['description'] ?? null);
        $status = $fields['status']['name'] ?? '';
        $created = $fields['created'] ?? '';

        $reporter = isset($fields['reporter']) ? $this->mapUser($fields['reporter']) : null;
        $assignee = isset($fields['assignee']) && is_array($fields['assignee']) ? $this->mapUser($fields['assignee']) : null;

        $attachments = [];
        if (isset($fields['attachment']) && is_array($fields['attachment'])) {
            foreach ($fields['attachment'] as $attach) {
                $attachments[] = $this->mapAttachment($attach);
            }
        }

        $comments = [];
        if (isset($fields['comment']['comments']) && is_array($fields['comment']['comments'])) {
            foreach ($fields['comment']['comments'] as $c) {
                $comments[] = $this->mapComment($c);
            }
        }

        $transitions = $this->getTransitionsForIssue((string)$issueData['id']);
        $assignableUserArrays = $this->getAssignableUsersForIssue((string)$issueData['id']);

        $assignableUsers = [];
        foreach ($assignableUserArrays as $userData) {
            $assignableUsers[] = $this->mapUser($userData);
        }

        return new Request(
            id: (string)$issueData['id'],
            key: $issueData['key'] ?? '',
            summary: $summary,
            description: $description,
            status: $status,
            reporter: $reporter,
            assignee: $assignee,
            created: $created,
            attachments: $attachments,
            comments: $comments,
            transitions: $transitions,
            assignableUsers: $assignableUsers
        );
    }

    private function mapUser(array $userData): User
    {
        return new User(
            accountId: $userData['accountId'] ?? '',
            emailAddress: $userData['emailAddress'] ?? '',
            displayName: $userData['displayName'] ?? '',
            active: isset($userData['active']) ? (bool)$userData['active'] : false
        );
    }

    private function mapAttachment(array $attachData): Attachment
    {
        $author = isset($attachData['author']) ? $this->mapUser($attachData['author']) : null;

        $attachment = new Attachment(
            id: (string)$attachData['id'],
            filename: $attachData['filename'] ?? '',
            author: $author,
            created: $attachData['created'] ?? '',
            size: (int)($attachData['size'] ?? 0),
            mimeType: $attachData['mimeType'] ?? '',
            contentUrl: $attachData['content'] ?? '',
            thumbnailUrl: $attachData['thumbnail'] ?? ''
        );

        $attachment->content = $this->fetchAttachmentContentById($attachment->id);

        return $attachment;
    }

    private function fetchAttachmentContentById(string $attachmentId): ?string
    {
        try {
            $endpoint = "attachment/content/{$attachmentId}";
            $response = $this->client->get($this->apiBasePath . $endpoint, [
                'headers' => [
                    'Accept' => '*/*'
                ],
                'stream' => false
            ]);

            $body = $response->getBody()->getContents();
            return $body;
        } catch (RequestException $e) {
            return null;
        }
    }

    private function mapComment(array $commentData): Comment
    {
        $author = $this->mapUser($commentData['author']);
        $updateAuthor = $this->mapUser($commentData['updateAuthor']);

        $bodyText = '';
        $attachments = [];
        if (isset($commentData['body']) && is_array($commentData['body'])) {
            [$bodyText, $attachments] = $this->extractCommentBodyData($commentData['body']);
        }

        $isPublic = isset($commentData['jsdPublic']) && $commentData['jsdPublic'] == 1;

        return new Comment(
            id: (string)$commentData['id'],
            author: $author,
            updateAuthor: $updateAuthor,
            bodyText: $bodyText,
            attachments: $attachments,
            created: $commentData['created'] ?? '',
            updated: $commentData['updated'] ?? '',
            isPublic: $isPublic
        );
    }

    private function extractCommentBodyData(array $body): array
    {
        $textParts = [];
        $attachments = [];

        if (isset($body['content']) && is_array($body['content'])) {
            foreach ($body['content'] as $node) {
                $this->parseBodyNode($node, $textParts, $attachments);
            }
        }

        return [implode("\n", $textParts), $attachments];
    }

    private function parseBodyNode(array $node, array &$textParts, array &$attachments): void
    {
        $type = $node['type'] ?? '';
        $content = $node['content'] ?? [];

        if ($type === 'paragraph' && is_array($content)) {
            $paragraphText = $this->extractTextFromContent($content);
            if ($paragraphText !== '') {
                $textParts[] = $paragraphText;
            }
        } elseif ($type === 'mediaSingle' && is_array($content)) {
            foreach ($content as $child) {
                if (($child['type'] ?? '') === 'media') {
                    $attachments[] = $this->mapMediaToAttachment($child);
                }
            }
        } else {
            if (is_array($content)) {
                foreach ($content as $child) {
                    $this->parseBodyNode($child, $textParts, $attachments);
                }
            }
        }
    }

    private function extractTextFromContent(array $contentNodes): string
    {
        $text = '';
        foreach ($contentNodes as $cn) {
            if (($cn['type'] ?? '') === 'text' && isset($cn['text'])) {
                $text .= $cn['text'];
            }
        }
        return $text;
    }

    private function mapMediaToAttachment(array $mediaNode): Attachment
    {
        $id = (string)($mediaNode['attrs']['id'] ?? '');
        $filename = 'embedded-file-' . $id;
        return new Attachment(
            id: $id,
            filename: $filename,
            author: null,
            created: '',
            size: 0,
            mimeType: '',
            contentUrl: '',
            thumbnailUrl: ''
        );
    }

    private function extractDescriptionText(?array $descriptionField): string
    {
        if (is_array($descriptionField) && isset($descriptionField['content']) && is_array($descriptionField['content'])) {
            $text = '';
            foreach ($descriptionField['content'] as $contentNode) {
                if (($contentNode['type'] ?? '') === 'paragraph' && isset($contentNode['content'])) {
                    foreach ($contentNode['content'] as $c) {
                        if (($c['type'] ?? '') === 'text' && isset($c['text'])) {
                            $text .= $c['text'];
                        }
                    }
                }
            }
            return $text;
        }
        return '';
    }

    private function getRequestTypes(): array
    {
        $serviceDeskId = $this->serviceDeskId;
        if (!$serviceDeskId) {
            return ['error' => 'serviceDeskId is required for retrieving request types'];
        }

        $response = $this->get("servicedesk/{$serviceDeskId}/requesttype", [], 'servicedesk');
        $issueTypes = [];
        if (isset($response['values'])) {
            foreach ($response['values'] as $issueType) {
                $issueTypes[] = [
                    'id' => $issueType['id'],
                    'name' => $issueType['name'],
                    'description' => $issueType['description'],
                ];
            }
        }

        return $issueTypes;
    }

    private function getRequestTypeIdByName($serviceDeskId, $typeName): ?string
    {
        $response = $this->get("servicedesk/{$serviceDeskId}/requesttype", [], 'servicedesk');


        if (!isset($response['values'])) {
            return null;
        }

        foreach ($response['values'] as $requestType) {
            if (strcasecmp($requestType['name'], $typeName) === 0) {
                return $requestType['id'];
            }
        }

        return null;
    }

    private function get(string $endpoint, array $params = [], string $apiType = 'api')
    {
        try {
            $basePath = $this->getBasePathByType($apiType);
            $options = ['query' => $params];
            $response = $this->client->get($basePath . $endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function post(string $endpoint, array $body = [], string $apiType = 'api')
    {
        try {
            $basePath = $this->getBasePathByType($apiType);
            $options = ['json' => $body];
            $response = $this->client->post($basePath . $endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function put(string $endpoint, array $body = [], string $apiType = 'api')
    {
        try {
            $basePath = $this->getBasePathByType($apiType);
            $options = ['json' => $body];
            $response = $this->client->put($basePath . $endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getBasePathByType(string $apiType): string
    {
        return $apiType === 'servicedesk' ? $this->serviceDeskBasePath : $this->apiBasePath;
    }

    // Enhanced Markdown-to-doc conversion methods with support for bold text
    private function markdownToDoc(string $markdown): array
    {
        $lines = explode("\n", trim($markdown));
        $content = [];
        $currentList = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                // Skip empty lines or handle as needed
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $matches)) {
                // Close any open list before heading
                if ($currentList !== null) {
                    $content[] = $currentList;
                    $currentList = null;
                }

                $headingLevel = strlen($matches[1]);
                $headingText = $matches[2];

                $content[] = $this->headingNode($headingText, $headingLevel);
                continue;
            }

            // List items
            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $matches)) {
                $itemText = $matches[1];
                if ($currentList === null) {
                    $currentList = $this->bulletListNode();
                }

                $currentList['content'][] = $this->listItemNode($itemText);
                continue;
            }

            // Paragraph
            if ($currentList !== null) {
                $content[] = $currentList;
                $currentList = null;
            }

            // Convert inline Markdown to doc format
            $paragraphContent = $this->parseInlineFormatting($trimmed);

            $content[] = [
                'type' => 'paragraph',
                'content' => $paragraphContent
            ];
        }

        if ($currentList !== null) {
            $content[] = $currentList;
        }

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $content
        ];
    }

    /**
     * Parses a string for inline Markdown formatting (e.g., bold) and converts it to Jira doc format.
     *
     * @param string $text The input text with Markdown formatting.
     * @return array The parsed content array with appropriate marks.
     */
    private function parseInlineFormatting(string $text): array
    {
        $result = [];
        $pattern = '/(\*\*|__)(.*?)\1/'; // Matches **bold** or __bold__

        $matches = [];
        $lastPos = 0;

        while (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE, $lastPos)) {
            $fullMatch = $matches[0][0];
            $fullPos = $matches[0][1];
            $boldText = $matches[2][0];
            $boldStart = $matches[2][1];

            // Text before the bold text
            if ($fullPos > $lastPos) {
                $normalText = substr($text, $lastPos, $fullPos - $lastPos);
                if ($normalText !== '') {
                    $result[] = [
                        'type' => 'text',
                        'text' => $normalText
                    ];
                }
            }

            // Bold text
            if ($boldText !== '') {
                $result[] = [
                    'type' => 'text',
                    'text' => $boldText,
                    'marks' => [
                        ['type' => 'strong']
                    ]
                ];
            }

            $lastPos = $fullPos + strlen($fullMatch);
        }

        // Text after the last bold text
        if ($lastPos < strlen($text)) {
            $normalText = substr($text, $lastPos);
            if ($normalText !== '') {
                $result[] = [
                    'type' => 'text',
                    'text' => $normalText
                ];
            }
        }

        return $result;
    }

    private function headingNode(string $text, int $level): array
    {
        // Convert inline Markdown in headings as well
        $headingContent = $this->parseInlineFormatting($text);

        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => $headingContent
        ];
    }

    private function paragraphNode(string $text): array
    {
        // Convert inline Markdown in paragraphs
        $paragraphContent = $this->parseInlineFormatting($text);

        return [
            'type' => 'paragraph',
            'content' => $paragraphContent
        ];
    }

    private function bulletListNode(): array
    {
        return [
            'type' => 'bulletList',
            'content' => []
        ];
    }

    private function listItemNode(string $text): array
    {
        // Convert inline Markdown in list items
        $itemContent = [
            [
                'type' => 'paragraph',
                'content' => $this->parseInlineFormatting($text)
            ]
        ];

        return [
            'type' => 'listItem',
            'content' => $itemContent
        ];
    }
}