<?php

namespace AssistantEngine\OpenFunctions\JiraServiceDesk;

use AssistantEngine\OpenFunctions\Core\Contracts\AbstractOpenFunction;
use AssistantEngine\OpenFunctions\Core\Helpers\FunctionDefinition;
use AssistantEngine\OpenFunctions\Core\Helpers\Parameter;
use AssistantEngine\OpenFunctions\Core\Models\Responses\BinaryResponseItem;
use AssistantEngine\OpenFunctions\Core\Models\Responses\TextResponseItem;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Parameters;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\CreateCardData;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\CreateCardParams;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\UpdateCardData;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Params\UpdateCardParams;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Clients\JiraServiceDeskClient;

class JiraServiceDeskOpenFunction extends AbstractOpenFunction
{
    private JiraServiceDeskClient $client;
    private Parameters $parameter;

    public function __construct(Parameters $parameter)
    {
        $this->parameter = $parameter;
        $this->initializeClient();
    }

    private function initializeClient()
    {
        $this->client = new JiraServiceDeskClient(
            $this->parameter->baseUri,
            $this->parameter->email,
            $this->parameter->apiToken,
            $this->parameter->projectKey
        );
    }

    public function showQueues(): string
    {
        return json_encode(['queues' => $this->client->getLists()]);
    }

    public function showCards(string $queueId): string
    {
        return json_encode($this->client->showCards($queueId));
    }

    public function getCard(string $cardId)
    {
        $responseItems = [];
        $binaryItems = [];

        $card = $this->client->getCard($cardId);


        foreach ($card->attachments as $attachment) {
            $binaryItems[] = new BinaryResponseItem($attachment->filename, base64_encode($attachment->content), $attachment->mimeType);
            $attachment->content = null;
        }

        $responseItems[] = new TextResponseItem(json_encode($card));

        return array_merge($responseItems, $binaryItems);
    }

    public function createCard(string $listName, string $type, string $name, string $desc)
    {
        return json_encode([
            "id" => $this->client->createCard(
                new CreateCardParams($listName, $type, new CreateCardData($name, $desc))
            )
        ]);
    }

    public function updateCard(string $cardId, string $name, string $desc)
    {
        return json_encode($this->client->updateCard(
            new UpdateCardParams($cardId, new UpdateCardData($name, $desc))
        ));
    }

    public function createComment(string $cardId, string $text, bool $isPublic): string
    {
        return json_encode($this->client->createComment($cardId, $text, $isPublic));
    }

    public function changeStatus(string $cardId, string $transitionId): string
    {
        return json_encode($this->client->changeStatus($cardId, $transitionId));
    }

    public function changePriority(string $cardId, string $priorityName): string
    {
        return json_encode($this->client->changePriority($cardId, $priorityName));
    }

    /**
     * Assign a user by display name (no enum for the user now, just a string).
     */
    public function assignUser(string $cardId, string $username): string
    {
        return json_encode($this->client->assignUser($cardId, $username));
    }

    public function generateFunctionDefinitions(): array
    {
        $result = [];

        // showLists
        $result["showQueues"] = (new FunctionDefinition("showQueues", 'List all queues on a specified service desk (Jira Service Desk).'))
            ->createFunctionDescription();

        // showCards
        $result["showCards"] = (new FunctionDefinition("showCards", 'List all requests (cards) in the specified queue (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('queueId')->description('The ID of the queue (list)')->required()
            )
            ->createFunctionDescription();

        // getCard
        $result["getCard"] = (new FunctionDefinition("getCard", 'Retrieve details of the specified request (card), including transitions and assignable users (Jira Service Desk). '))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to retrieve')->required()
            )
            ->createFunctionDescription();

        // Card data param
        $cardDataParam = Parameter::object('data')->description('Data for the request (card)')->required();
        $cardDataParam->addProperty(Parameter::string('name')->description('The summary of the request')->required())
            ->addProperty(Parameter::string('desc')->description('The description of the request')->required());

        // getTypes for request creation
        $typesEnum = [];
        $typesResponse = $this->getTypes();
        if (is_array($typesResponse)) {
            foreach ($typesResponse as $type) {
                if (isset($type['name'])) {
                    $typesEnum[] = $type['name'];
                }
            }
        }

        $typeParameter = Parameter::string('type')
            ->description('The request type to create')
            ->required()
            ->enum($typesEnum);

        // updateCard
        $result["updateCard"] = (new FunctionDefinition("updateCard", 'Update data of a request (card) (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to update')->required()
            )
            ->addParameter($cardDataParam)
            ->createFunctionDescription();

        // createCard
        $result["createCard"] = (new FunctionDefinition("createCard", 'Create a new request (card) in a specified queue (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('listName')->description('The name of the queue (list) to add the card/request to')->required()
            )
            ->addParameter($typeParameter)
            ->addParameter($cardDataParam)
            ->createFunctionDescription();

        // createComment
        $result["createComment"] = (new FunctionDefinition("createComment", 'Add a public or internal comment to a request (card) (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to comment on')->required()
            )
            ->addParameter(
                Parameter::string('text')->description('The comment text')->required()
            )
            ->addParameter(
                Parameter::boolean('isPublic')->description('Whether the comment is public (true) or internal/private (false)')->required()
            )
            ->createFunctionDescription();

        // changeStatus
        $result["changeStatus"] = (new FunctionDefinition("changeStatus", 'Transition a request (card) to a different status (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to transition')->required()
            )
            ->addParameter(
                Parameter::string('transitionId')->description('The desired transition ID to move the request to another status')->required()
            )
            ->createFunctionDescription();

        // changePriority
        $priorityEnum = $this->getPrioritiesForEnum();
        $priorityParam = Parameter::string('priorityName')->description('The new priority name')->required();
        if (!empty($priorityEnum)) {
            $priorityParam->enum($priorityEnum);
        }

        $result["changePriority"] = (new FunctionDefinition("changePriority", 'Change the priority of a request (card) (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to change priority')->required()
            )
            ->addParameter($priorityParam)
            ->createFunctionDescription();

        // assignUser (no enum for users now, just a string)
        $result["assignUser"] = (new FunctionDefinition("assignUser", 'Assign the request (card) to another user by providing the username as a string (Jira Service Desk).'))
            ->addParameter(
                Parameter::string('cardId')->description('The ID of the card/request to assign')->required()
            )
            ->addParameter(
                Parameter::string('username')->description('The display name of the user to assign')->required()
            )
            ->createFunctionDescription();

        return $result;
    }

    private function getTypes(): array
    {
        return $this->client->getTypes();
    }

    /**
     * Fetch all priorities for enum generation.
     */
    private function getPrioritiesForEnum(): array
    {
        $priorities = $this->client->getPriorities();
        $names = [];
        foreach ($priorities as $p) {
            if (isset($p['name'])) {
                $names[] = $p['name'];
            }
        }
        return $names;
    }
}