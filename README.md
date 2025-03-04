# Jira Service Desk Open Function

The Jira Service Desk Open Function is an implementation of the [Open Functions architecture](https://github.com/AssistantEngine/open-functions-core) designed to seamlessly integrate with Jira Service Desk. This package allows language models (LLMs) to interact with Jira Service Desk—creating requests, updating issues, managing queues, adding comments, and more—via a simple and structured interface.

## Installation

Install the Jira Service Desk Open Function via Composer:

```bash
composer require assistant-engine/open-functions-jira-service-desk
```

## Usage

### Using the OpenAI PHP SDK

Below is a basic example of how to integrate and use the Jira Service Desk Open Function with the OpenAI PHP SDK:

```php
<?php

use AssistantEngine\OpenFunctions\JiraServiceDesk\JiraServiceDeskOpenFunction;
use AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Parameters;
use OpenAI;

// Set up parameters for Jira Service Desk authentication
$parameters = new Parameters();
$parameter->baseUri = env('JSD_BASE_URI');
$parameter->email = env('JSD_EMAIL');
$parameter->apiToken = env('JSD_TOKEN');
$parameter->projectKey = env('JSD_PROJECT_KEY');

// Initialize the Jira Service Desk Open Function
$jiraServiceDeskFunction = new JiraServiceDeskOpenFunction($parameters);

// Generate function definitions for tool integration with an LLM
$functionDefinitions = $jiraServiceDeskFunction->generateFunctionDefinitions();

$client = OpenAI::client(env('OPENAI_TOKEN'));

$result = $client->chat()->create([
    'model' => 'gpt-4o',
    'messages' => [],
    'tools' => $functionDefinitions
]);

$choice = $result->choices[0];

if ($choice->finishReason === 'tool_calls') {
    $toolCalls = processToolCalls($choice->message->toolCalls, $jiraServiceDeskFunction);
} 

function processToolCalls($toolCalls, $jiraServiceDeskFunction)
{
    foreach ($toolCalls as $toolCall) {
        $functionName = $toolCall->function->name;
        $functionArgs = json_decode($toolCall->function->arguments, true);
        $response = $jiraServiceDeskFunction->callMethod($functionName, $functionArgs);
        // Process each response as needed
    }
}
```

### Integration with the Filament Assistant Plugin

You can also integrate the Jira Service Desk Open Function as a tool within the [Filament Assistant Plugin](https://github.com/AssistantEngine/filament-assistant). Simply add the following configuration to your `config/filament-assistant.php` file:

```php
// inside config/filament-assistant.php

'tools' => [
    'jira_service_desk' => [
        'namespace'   => 'jsd',
        'description' => 'Tool for interacting with Jira Service Desk requests.',
        'tool'        => function () {
            $parameter = new \AssistantEngine\OpenFunctions\JiraServiceDesk\Models\Parameters();
            $parameter->baseUri = env('JSD_BASE_URI');
            $parameter->email = env('JSD_EMAIL');
            $parameter->apiToken = env('JSD_TOKEN');
            $parameter->projectKey = env('JSD_PROJECT_KEY');

            return new \AssistantEngine\OpenFunctions\JiraServiceDesk\JiraServiceDeskOpenFunction($parameter);
        },
    ]
]
```

With this setup, your assistant can directly use Jira Service Desk functions to create, update, and manage requests.

![Demo Assistant Example](media/chat.png)

## Methods

Below is a summary of the main methods available in the Jira Service Desk Open Function:

| **Method**         | **Description**                                                                                                                      | **Parameters**                                                                                                                                                                                                                                                                                                      |
|--------------------|--------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **showQueues**     | Lists all queues available on the specified Jira Service Desk.                                                                     | _No parameters required_                                                                                                                                                                                                                                                                                             |
| **showCards**      | Lists all requests (cards) in a specified queue.                                                                                     | **queueId**: *string* (required)                                                                                                                                                                                                                                                                                     |
| **getCard**        | Retrieves details of a request (card), including attachments, comments, transitions, and assignable users.                           | **cardId**: *string* (required)                                                                                                                                                                                                                                                                                      |
| **createCard**     | Creates a new request (card) in a specified queue.                                                                                   | **listName**: *string* (required); <br> **type**: *string* (required, must be one of the allowed request types); <br> **data**: *object* (required) <br>&nbsp;&nbsp;&nbsp;&nbsp;• **name**: *string* (request summary) <br>&nbsp;&nbsp;&nbsp;&nbsp;• **desc**: *string* (request description in Markdown)  |
| **updateCard**     | Updates an existing request (card).                                                                                                  | **cardId**: *string* (required); <br> **data**: *object* (required) <br>&nbsp;&nbsp;&nbsp;&nbsp;• **name**: *string* (updated summary) <br>&nbsp;&nbsp;&nbsp;&nbsp;• **desc**: *string* (updated description in Markdown)                                                                                     |
| **createComment**  | Adds a comment (public or internal) to a request (card).                                                                             | **cardId**: *string* (required); <br> **text**: *string* (required); <br> **isPublic**: *boolean* (required)                                                                                                                                                                                                      |
| **changeStatus**   | Transitions a request (card) to a different status.                                                                                  | **cardId**: *string* (required); <br> **transitionId**: *string* (required)                                                                                                                                                                                                                                          |
| **changePriority** | Changes the priority of a request (card).                                                                                            | **cardId**: *string* (required); <br> **priorityName**: *string* (required, choose from allowed priorities)                                                                                                                                                                                                         |
| **assignUser**     | Assigns a request (card) to another user by providing the user's display name.                                                       | **cardId**: *string* (required); <br> **username**: *string* (required)                                                                                                                                                                                                                                              |

## More Repositories

We’ve created more repositories to make AI integration even simpler and more powerful! Check them out:

- **[Open Functions Core](https://github.com/AssistantEngine/open-functions-core)**: Open Functions provide a standardized way to implement and invoke functions for tool calling with large language models (LLMs).

> We are a young startup aiming to make it easy for developers to add AI to their applications. We welcome feedback, questions, comments, and contributions. Feel free to contact us at [contact@assistant-engine.com](mailto:contact@assistant-engine.com).

## Consultancy & Support

Do you need assistance integrating Open Functions into your application, or help setting it up?  
We offer consultancy services to help you get the most out of our package, whether you’re just getting started or looking to optimize an existing setup.

Reach out to us at [contact@assistant-engine.com](mailto:contact@assistant-engine.com).

## Contributing

We welcome contributions from the community! Feel free to submit pull requests, open issues, and help us improve the package.

## License

This project is licensed under the MIT License. Please see [License File](LICENSE.md) for more information.
